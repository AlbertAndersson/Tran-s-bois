"""Synthetic health checks for the private offsite relay status."""
from pathlib import Path
import importlib.util
import json
import tempfile
import unittest
from datetime import datetime, timezone, timedelta

spec = importlib.util.spec_from_file_location(
    "health", Path(__file__).resolve().parents[1] / "scripts/p18-offsite-health.py"
)
health = importlib.util.module_from_spec(spec)
spec.loader.exec_module(health)


class HealthTest(unittest.TestCase):
    def setUp(self):
        self.temp = tempfile.TemporaryDirectory(prefix="bois-health-test-")
        self.addCleanup(self.temp.cleanup)
        self.private = Path(self.temp.name)
        self.now = datetime(2026, 10, 7, 12, 0, tzinfo=timezone.utc)

    def write(self, **updates):
        status = {
            "ok": True,
            "verified_at": (self.now - timedelta(hours=1)).isoformat(),
            "backup_created_at": (self.now - timedelta(hours=2)).isoformat(),
            "offsite_readback_verified": True,
            "deletions": 0,
        }
        status.update(updates)
        (self.private / "p18-offsite-relay-status.json").write_text(
            json.dumps(status), encoding="utf-8"
        )

    def test_healthy_recent_verified_backup(self):
        self.write()
        report = health.assess(self.private, 30, self.now)
        self.assertTrue(report["ok"])
        self.assertEqual(report["deletions"], 0)

    def test_stale_verified_backup_fails_closed(self):
        self.write(verified_at=(self.now - timedelta(hours=31)).isoformat())
        with self.assertRaises(RuntimeError):
            health.assess(self.private, 30, self.now)

    def test_failed_relay_status_fails_closed(self):
        self.write(ok=False)
        with self.assertRaises(RuntimeError):
            health.assess(self.private, 30, self.now)

    def test_unverified_readback_fails_closed(self):
        self.write(offsite_readback_verified=False)
        with self.assertRaises(RuntimeError):
            health.assess(self.private, 30, self.now)

    def test_unexpected_deletion_fails_closed(self):
        self.write(deletions=1)
        with self.assertRaises(RuntimeError):
            health.assess(self.private, 30, self.now)

    def test_future_timestamp_is_rejected(self):
        self.write(verified_at=(self.now + timedelta(minutes=10)).isoformat())
        with self.assertRaises(RuntimeError):
            health.assess(self.private, 30, self.now)


if __name__ == "__main__":
    unittest.main()
