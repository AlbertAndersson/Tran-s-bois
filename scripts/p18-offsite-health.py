"""Fail-closed health check for the Windows -> Besovida P18 offsite relay.

Reads only the private relay status file. It does not connect to production,
send notifications, delete backups, or expose credentials.
"""
from __future__ import annotations

from pathlib import Path
import argparse
import json
from datetime import datetime, timezone


def _time(value: str) -> datetime:
    parsed = datetime.fromisoformat(value.replace("Z", "+00:00"))
    if parsed.tzinfo is None:
        raise ValueError("timezone-aware timestamp required")
    return parsed.astimezone(timezone.utc)


def assess(private_dir: Path, max_age_hours: float = 30.0, now: datetime | None = None) -> dict:
    private = private_dir.resolve(strict=True)
    if "onedrive" in str(private).lower():
        raise RuntimeError("Private directory cannot be in OneDrive")
    if max_age_hours <= 0 or max_age_hours > 168:
        raise ValueError("max-age-hours must be > 0 and <= 168")

    status_path = private / "p18-offsite-relay-status.json"
    status = json.loads(status_path.read_text(encoding="utf-8"))
    if status.get("ok") is not True:
        raise RuntimeError("Latest relay attempt is not successful")
    if status.get("offsite_readback_verified") is not True:
        raise RuntimeError("Offsite readback is not verified")
    if status.get("deletions") != 0:
        raise RuntimeError("Relay status must confirm zero deletions")

    current = (now or datetime.now(timezone.utc)).astimezone(timezone.utc)
    verified = _time(status["verified_at"])
    created = _time(status["backup_created_at"])
    newest = max(verified, created)
    future_seconds = (newest - current).total_seconds()
    if future_seconds > 300:
        raise RuntimeError("Relay timestamps are unexpectedly in the future")
    verified_age_hours = max(0.0, (current - verified).total_seconds() / 3600)
    backup_age_hours = max(0.0, (current - created).total_seconds() / 3600)
    if verified_age_hours > max_age_hours or backup_age_hours > max_age_hours:
        raise RuntimeError("Verified offsite backup is stale")

    return {
        "ok": True,
        "checked_at": current.isoformat(),
        "verified_age_hours": round(verified_age_hours, 2),
        "backup_age_hours": round(backup_age_hours, 2),
        "max_age_hours": max_age_hours,
        "offsite_readback_verified": True,
        "deletions": 0,
    }


def main() -> int:
    parser = argparse.ArgumentParser()
    parser.add_argument("--private-dir", required=True)
    parser.add_argument("--max-age-hours", type=float, default=30.0)
    args = parser.parse_args()
    try:
        report = assess(Path(args.private_dir), args.max_age_hours)
        code = 0
    except Exception:
        report = {
            "ok": False,
            "checked_at": datetime.now(timezone.utc).isoformat(),
            "reason": "Offsite backup health requires owner inspection; no credentials emitted",
        }
        code = 2
    print(json.dumps(report))
    return code


if __name__ == "__main__":
    raise SystemExit(main())
