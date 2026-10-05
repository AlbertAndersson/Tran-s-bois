"""Copy and authenticate encrypted BoIS backups using existing private SSH keys.

No scheduling, credential provisioning, deletion or production activation occurs.
Run on Albert's trusted Windows relay, with cryptography installed and the private
directory outside the repository/OneDrive. Raw SSH errors and key data stay private.
"""
from pathlib import Path
import argparse
import hashlib
import io
import json
import os
import re
import subprocess
import sys
import tarfile
import uuid
from datetime import datetime, timezone

from cryptography.hazmat.primitives.ciphers.aead import AESGCM


def transfer(private: Path) -> dict:
    if sys.platform != 'win32':
        raise RuntimeError('This relay requires the approved Windows host')
    private = private.resolve(strict=True)
    if 'onedrive' in str(private).lower():
        raise RuntimeError('Private directory cannot be in OneDrive')
    lock = private / 'p18-offsite-relay.lock'
    lock_fd = os.open(lock, os.O_CREAT | os.O_EXCL | os.O_WRONLY)
    work = private / ('p18-relay-' + uuid.uuid4().hex)
    try:
        work.mkdir()
        def execute(args, stdin=None, absent_ok=False):
            result = subprocess.run(args, input=stdin, text=True, capture_output=True, timeout=90)
            if result.returncode != 0:
                if absent_ok and 'No such file' in result.stderr:
                    return None
                raise RuntimeError('Private transfer failed; owner inspection required')
            return result.stdout
        def options(key, hosts):
            return ['-o', 'BatchMode=yes', '-o', 'StrictHostKeyChecking=yes',
                    '-o', 'ConnectTimeout=15', '-o', 'UserKnownHostsFile=' + str(private / hosts),
                    '-i', str(private / key)]
        source = options('id_ed25519_socen', 'known_hosts')
        destination = options('id_ed25519_besovida_backup', 'besovida-known-hosts')
        raw = execute(['ssh', *source, 'socen.se@ssh.simply.com',
                       'cat /var/www/socen.se/.bois-production/p18-backup-status.json'])
        status = json.loads(raw)
        name = status.get('file', '')
        expected = status.get('sha256', '')
        if status.get('ok') is not True or not re.fullmatch(r'bois-daily-\d{8}T\d{6}Z-[a-f0-9]{7}\.aesgcm', name):
            raise RuntimeError('Verified backup status required')
        if not re.fullmatch(r'[a-f0-9]{64}', expected):
            raise RuntimeError('Backup checksum required')
        age = (datetime.now(timezone.utc) - datetime.fromisoformat(status['created_at'])).total_seconds()
        if age < -300 or age > 24 * 3600:
            raise RuntimeError('Backup freshness outside the accepted 24-hour target')
        local = work / name
        execute(['scp', *source, 'socen.se@ssh.simply.com:/var/www/socen.se/.bois-production/daily-encrypted/' + name, str(local)])
        data = local.read_bytes()
        if len(data) > 101 * 1024 * 1024 or hashlib.sha256(data).hexdigest() != expected:
            raise RuntimeError('Bounded exact backup checksum required')
        header = b'BOIS_BACKUP_AES256_GCM_V1\n'
        key = (private / 'p18-backup-recovery.key').read_bytes()
        if not data.startswith(header) or len(key) != 32:
            raise RuntimeError('Authenticated envelope and separate recovery key required')
        plain = AESGCM(key).decrypt(data[len(header):len(header)+12], data[len(header)+12:], header)
        with tarfile.open(fileobj=io.BytesIO(plain)) as archive:
            if set(archive.getnames()) != {'production.sql', 'runtime-release.tar', 'manifest.json', 'schedule-private.txt'}:
                raise RuntimeError('Expected backup file manifest required')
            if any(not member.isreg() for member in archive.getmembers()):
                raise RuntimeError('Only regular backup members allowed')
            manifest = json.load(archive.extractfile('manifest.json'))
            if manifest['source_revision'] != status['source_revision']:
                raise RuntimeError('Exact backup revision required')
            if set(manifest['files']) != {'production.sql', 'runtime-release.tar', 'schedule-private.txt'}:
                raise RuntimeError('Exact inner checksums required')
            for file, digest in manifest['files'].items():
                if hashlib.sha256(archive.extractfile(file).read()).hexdigest() != digest:
                    raise RuntimeError('Internal backup checksum mismatch')
        readback = work / ('readback-' + name)
        def batch(text, absent_ok=False):
            return execute(['sftp', *destination, '-b', '-', 'albert@100.66.179.74'], text, absent_ok)
        remote = '/home/albert/Bois/' + name
        def quote(path):
            value = str(path).replace('\\', '/')
            if any(c in value for c in ['"', '\n', '\r']):
                raise RuntimeError('Safe transfer path required')
            return '"' + value + '"'
        existing = batch('get ' + remote + ' ' + quote(readback) + '\n', absent_ok=True)
        if existing is None:
            temporary = remote + '.part-' + uuid.uuid4().hex
            batch('put ' + quote(local) + ' ' + temporary + '\nchmod 600 ' + temporary +
                  '\nrename ' + temporary + ' ' + remote + '\nget ' + remote + ' ' + quote(readback) + '\n')
        if hashlib.sha256(readback.read_bytes()).hexdigest() != expected:
            raise RuntimeError('Offsite readback checksum mismatch; no existing backup is deleted')
        copied = readback.read_bytes()
        if AESGCM(key).decrypt(copied[len(header):len(header)+12], copied[len(header)+12:], header) != plain:
            raise RuntimeError('Offsite authenticated readback mismatch')
        return {'ok': True, 'verified_at': datetime.now(timezone.utc).isoformat(),
                'backup_created_at': status['created_at'], 'file': name, 'sha256': expected,
                'source_revision': status['source_revision'], 'offsite_readback_verified': True,
                'scheduler_installed_by_script': False, 'deletions': 0}
    finally:
        os.close(lock_fd)
        lock.unlink()


if __name__ == '__main__':
    parser = argparse.ArgumentParser()
    parser.add_argument('--private-dir', required=True)
    args = parser.parse_args()
    private_dir = Path(args.private_dir)
    try:
        report = transfer(private_dir)
        result_code = 0
    except Exception:
        report = {'ok': False, 'at': datetime.now(timezone.utc).isoformat(),
                  'reason': 'Inspect private relay prerequisites; no credentials emitted', 'deletions': 0}
        result_code = 1
    # The report contains no credentials, DB rows or recovery material.
    (private_dir / 'p18-offsite-relay-status.json').write_text(json.dumps(report, indent=2) + '\n', encoding='utf-8')
    print(json.dumps(report))
    raise SystemExit(result_code)
