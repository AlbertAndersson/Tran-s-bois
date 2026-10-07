"""Synthetic relay integration tests: no network or production credentials."""
from pathlib import Path
import hashlib
import importlib.util
import io
import json
import shlex
import subprocess
import tarfile
import tempfile
import unittest
from datetime import datetime, timezone, timedelta
from unittest.mock import patch
from cryptography.hazmat.primitives.ciphers.aead import AESGCM

spec = importlib.util.spec_from_file_location('relay', Path(__file__).resolve().parents[1] / 'scripts/p18-offsite-relay.py')
relay = importlib.util.module_from_spec(spec)
spec.loader.exec_module(relay)


class RelayTest(unittest.TestCase):
    def setUp(self):
        self.temp = tempfile.TemporaryDirectory(prefix='bois-relay-test-')
        self.addCleanup(self.temp.cleanup)
        self.private = Path(self.temp.name)
        self.key = AESGCM.generate_key(bit_length=256)
        (self.private / 'p18-backup-recovery.key').write_bytes(self.key)
        self.revision = 'c' * 40
        files = {'production.sql': b'Synthetic SQL only', 'runtime-release.tar': b'Synthetic runtime only', 'schedule-private.txt': b'Synthetic schedule only'}
        files['manifest.json'] = json.dumps({'source_revision': self.revision, 'files': {p: hashlib.sha256(b).hexdigest() for p, b in files.items()}}).encode()
        buffer = io.BytesIO()
        with tarfile.open(fileobj=buffer, mode='w') as tar:
            for name, data in files.items():
                item = tarfile.TarInfo(name); item.size = len(data)
                tar.addfile(item, io.BytesIO(data))
        header = b'BOIS_BACKUP_AES256_GCM_V1\n'
        nonce = b'N' * 12
        self.data = header + nonce + AESGCM(self.key).encrypt(nonce, buffer.getvalue(), header)
        self.status = {'ok': True, 'file': 'bois-daily-20261006T030000Z-ccccccc.aesgcm', 'sha256': hashlib.sha256(self.data).hexdigest(),
                       'source_revision': self.revision, 'created_at': datetime.now(timezone.utc).isoformat()}
        self.remote = {}
        self.writes = 0
        self.network_calls = 0

    def process(self, args, input=None, **kwargs):
        self.network_calls += 1
        if args[0] == 'ssh':
            return subprocess.CompletedProcess(args, 0, json.dumps(self.status), '')
        if args[0] == 'scp':
            Path(args[-1]).write_bytes(self.data)
            return subprocess.CompletedProcess(args, 0, '', '')
        assert args[0] == 'sftp'
        for line in input.splitlines():
            parts = shlex.split(line)
            if parts[0] == 'get':
                if parts[1] not in self.remote:
                    return subprocess.CompletedProcess(args, 1, '', f'File "{parts[1]}" not found.\n')
                Path(parts[2]).write_bytes(self.remote[parts[1]])
            elif parts[0] == 'put':
                self.writes += 1
                self.remote[parts[2]] = Path(parts[1]).read_bytes()
            elif parts[0] == 'rename':
                assert parts[2] not in self.remote
                self.remote[parts[2]] = self.remote.pop(parts[1])
            else:
                assert parts[0] == 'chmod' and parts[1] == '600'
        return subprocess.CompletedProcess(args, 0, '', '')

    def run_relay(self):
        with patch.object(relay.sys, 'platform', 'win32'), patch.object(relay.subprocess, 'run', self.process):
            return relay.transfer(self.private)

    def test_success_and_repeat_readback_preserves_existing(self):
        report = self.run_relay()
        self.assertTrue(report['offsite_readback_verified'])
        self.assertEqual(self.writes, 1)
        self.run_relay()
        self.assertEqual(self.writes, 1)
        self.assertFalse((self.private / 'p18-offsite-relay.lock').exists())

    def test_modified_authenticated_tag_never_uploaded(self):
        self.data = self.data[:-1] + bytes([self.data[-1] ^ 1])
        self.status['sha256'] = hashlib.sha256(self.data).hexdigest()
        with self.assertRaises(Exception): self.run_relay()
        self.assertEqual(self.writes, 0)

    def test_permission_failure_is_not_treated_as_missing(self):
        original = self.process
        def denied(args, **kwargs):
            if args[0] == 'sftp':
                return subprocess.CompletedProcess(args, 1, '', 'Permission denied')
            return original(args, **kwargs)
        self.process = denied
        with self.assertRaises(RuntimeError): self.run_relay()
        self.assertEqual(self.writes, 0)

    def test_stale_backup_never_downloaded_or_uploaded(self):
        self.status['created_at'] = (datetime.now(timezone.utc) - timedelta(hours=25)).isoformat()
        with self.assertRaises(RuntimeError): self.run_relay()
        self.assertEqual(self.network_calls, 1)
        self.assertEqual(self.writes, 0)

    def test_existing_corrupt_offsite_is_not_overwritten(self):
        self.remote['/home/albert/Bois/' + self.status['file']] = b'corrupt existing file'
        with self.assertRaises(RuntimeError): self.run_relay()
        self.assertEqual(self.writes, 0)

    def test_filename_path_escape_refused(self):
        self.status['file'] = '../config-p18.php'
        with self.assertRaises(RuntimeError): self.run_relay()
        self.assertEqual(self.network_calls, 1)
        self.assertEqual(self.writes, 0)

    def test_locked_relay_does_not_touch_network_or_remove_foreign_lock(self):
        lock = self.private / 'p18-offsite-relay.lock'; lock.write_text('another process')
        with self.assertRaises(FileExistsError): self.run_relay()
        self.assertEqual(self.network_calls, 0)
        self.assertTrue(lock.exists())


if __name__ == '__main__': unittest.main()
