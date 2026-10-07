"""Static safety checks for the Windows P18 scheduler scripts.

The GitHub runner is Linux, so these checks validate the security invariants
and opt-in installation model without pretending to execute Task Scheduler.
"""
from pathlib import Path
import unittest

ROOT = Path(__file__).resolve().parents[1]


class WindowsTaskStaticTest(unittest.TestCase):
    def test_installer_is_explicit_opt_in_and_limited(self):
        text = (ROOT / "scripts/install-p18-offsite-task.ps1").read_text(encoding="utf-8")
        self.assertIn("[switch]$Install", text)
        self.assertIn("PREVIEW ONLY", text)
        self.assertIn("-LogonType Interactive", text)
        self.assertIn("-RunLevel Limited", text)
        self.assertNotIn("-RunLevel Highest", text)
        self.assertIn("PT6H", text)
        self.assertIn("-StartWhenAvailable", text)

    def test_cycle_writes_private_failure_marker_without_external_alert(self):
        text = (ROOT / "scripts/p18-offsite-cycle.ps1").read_text(encoding="utf-8")
        self.assertIn("p18-offsite-alert.json", text)
        self.assertIn("external_notification_sent = $false", text)
        self.assertIn("msg.exe", text)
        self.assertNotIn("Send-MailMessage", text)
        self.assertNotIn("Invoke-WebRequest", text)
        self.assertNotIn("Invoke-RestMethod", text)


if __name__ == "__main__":
    unittest.main()
