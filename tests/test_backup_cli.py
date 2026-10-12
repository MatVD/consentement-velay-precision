"""Isolated checks of backup archive validation, without DB or customer data."""
import hashlib
import importlib.util
import io
import json
from pathlib import Path
import tarfile
import tempfile
import unittest

ROOT = Path(__file__).resolve().parents[1]
SPEC = importlib.util.spec_from_file_location("velay_backup", ROOT / "scripts" / "backup.py")
BACKUP = importlib.util.module_from_spec(SPEC)
SPEC.loader.exec_module(BACKUP)


class BackupCliTest(unittest.TestCase):
    def setUp(self):
        # Restrict all fixture writes to the allowed project root.
        self.temp = tempfile.TemporaryDirectory(prefix="test-vbackup-", dir=ROOT / "var")
        self.folder = Path(self.temp.name)

    def tearDown(self):
        self.temp.cleanup()

    def test_restricts_database_names(self):
        self.assertEqual("velay", BACKUP.validate_db("velay"))
        self.assertEqual("velay_phase3_fixture_abcd", BACKUP.validate_db("velay_phase3_fixture_abcd"))
        for name in ("velay_test", "postgres", "velay;drop database velay", "velay/../"):
            with self.subTest(name=name), self.assertRaises(BACKUP.BackupError):
                BACKUP.validate_db(name)

    def test_evidence_tar_verifies_hashes(self):
        archive = self.folder / "evidence.tar"
        payload = b"%PDF-1.4\nsynthetic evidence only\n"
        with tarfile.open(archive, "w") as out:
            file = tarfile.TarInfo("./receipt.pdf")
            file.size = len(payload)
            out.addfile(file, io.BytesIO(payload))
        self.assertEqual(
            {"receipt.pdf": hashlib.sha256(payload).hexdigest()},
            BACKUP.safe_evidence_paths(archive),
        )

    def test_evidence_tar_rejects_traversal(self):
        archive = self.folder / "evidence.tar"
        with tarfile.open(archive, "w") as out:
            file = tarfile.TarInfo("../unexpected.pdf")
            file.size = 1
            out.addfile(file, io.BytesIO(b"x"))
        with self.assertRaises(BACKUP.BackupError):
            BACKUP.safe_evidence_paths(archive)

    def test_manifest_hashes_are_checked(self):
        evidence = self.folder / "evidence.tar"
        with tarfile.open(evidence, "w") as file:
            content = b"%PDF-1.4 synthetic only"
            entry = tarfile.TarInfo("fake.pdf")
            entry.size = len(content)
            file.addfile(entry, io.BytesIO(content))
        database = self.folder / "database.dump"
        database.write_bytes(b"fixture pg_dump bytes only")
        manifest = {
            "schema_version": 1,
            "source_database": "velay_phase3_fixture_unit",
            "files": {
                file.name: {"sha256": BACKUP.hash_file(file), "bytes": file.stat().st_size}
                for file in (evidence, database)
            },
        }
        packed = self.folder / "package.tar.gz"
        with tarfile.open(packed, "w:gz") as bundle:
            document = json.dumps(manifest).encode()
            info = tarfile.TarInfo("manifest.json")
            info.size = len(document)
            bundle.addfile(info, io.BytesIO(document))
            for file in (evidence, database):
                bundle.add(file, arcname=file.name)
        extracted = self.folder / "verified"
        extracted.mkdir()
        info, hashes = BACKUP.unpack_verified(packed, extracted)
        self.assertEqual(manifest["source_database"], info["source_database"])
        self.assertEqual(1, len(hashes))
        database.write_bytes(b"tampered source does not affect tar contents")
        self.assertTrue((extracted / "database.dump").exists())

    def test_rejects_destination_inside_project(self):
        self.assertRaises(BACKUP.BackupError, BACKUP.ensure_backup_destination, str(self.folder))


if __name__ == "__main__":
    unittest.main()
