#!/usr/bin/env python3
"""Encrypted DB + evidence backup; isolated restore validation (Python 3.10+).

No plaintext backup is left outside an automatically cleaned, mode-0700
temporary directory. Encryption uses the app's PHP/libsodium secretstream,
and VELAY_BACKUP_SECRET is inherited by the Docker process, never an argv value.

Run only when the application is quiescent: pg_dump and file snapshots are not
an atomic cross-volume transaction.
"""
from __future__ import annotations

import argparse
import datetime as dt
import hashlib
import json
import os
from pathlib import Path, PurePosixPath
import re
import secrets
import subprocess
import sys
import tarfile
import tempfile

ROOT = Path(__file__).resolve().parent.parent
# exec targets an existing service; the base Compose file works for both
# dev and prod deployments without accidentally loading dev-only settings.
COMPOSE = ["docker", "compose", "-f", "compose.yaml"]
MAGIC_VERSION = 1
DB_PATTERN = re.compile(r"velay(?:_phase3_[a-z0-9_]+)?\Z")
TEST_RESTORE_PATTERN = re.compile(r"velay_phase3_restore_[a-z0-9]+\Z")
ARCHIVE_MEMBERS = {"manifest.json", "database.dump", "evidence.tar"}


class BackupError(Exception):
    pass


def require_secret() -> None:
    secret = os.environ.get("VELAY_BACKUP_SECRET", "")
    if len(secret.encode("utf-8")) < 24:
        raise BackupError("VELAY_BACKUP_SECRET doit contenir au moins 24 octets, sans être placé dans le dépôt.")


def run_to_file(cmd: list[str], output: Path, source: Path | None = None) -> None:
    with output.open("wb") as stream:
        output.chmod(0o600)
        with source.open("rb") if source is not None else open(os.devnull, "rb") as input_stream:
            result = subprocess.run(
                cmd, cwd=ROOT, stdin=input_stream, stdout=stream,
                stderr=subprocess.PIPE, check=False,
            )
    if result.returncode:
        output.unlink(missing_ok=True)
        raise BackupError("Une commande de sauvegarde a échoué (code {}).".format(result.returncode))


def run(cmd: list[str]) -> bytes:
    p = subprocess.run(cmd, cwd=ROOT, stdout=subprocess.PIPE, stderr=subprocess.PIPE, check=False)
    if p.returncode:
        raise BackupError("Une commande de restauration a échoué (code {}).".format(p.returncode))
    return p.stdout


def hash_file(path: Path) -> str:
    digest = hashlib.sha256()
    with path.open("rb") as stream:
        for chunk in iter(lambda: stream.read(1024 * 1024), b""):
            digest.update(chunk)
    return digest.hexdigest()


def validate_db(name: str) -> str:
    if not DB_PATTERN.fullmatch(name):
        raise BackupError("Nom de base de données non autorisé.")
    return name


def make_fixture_tar(source: Path, destination: Path) -> None:
    if not source.is_dir() or source.is_symlink():
        raise BackupError("Le dossier de pièces de recette est invalide.")
    with tarfile.open(destination, "w") as archive:
        for file in sorted(source.iterdir()):
            if file.is_symlink() or not file.is_file() or '/' in file.name or file.name.startswith('.'):
                raise BackupError("Les pièces de recette doivent être des fichiers ordinaires à plat.")
            archive.add(file, arcname=file.name, recursive=False)
    destination.chmod(0o600)


def ensure_backup_destination(dir_name: str) -> Path:
    directory = Path(dir_name).expanduser().resolve()
    if directory == ROOT or ROOT in directory.parents:
        raise BackupError("La destination ne doit pas se trouver dans le dépôt du projet.")
    if not directory.is_dir() or directory.is_symlink():
        raise BackupError("Créer d'abord le dossier de destination externe, avec des droits limités.")
    if directory.stat().st_mode & 0o077:
        raise BackupError("Dossier de sauvegarde trop ouvert : exiger chmod 700.")
    return directory


def create(args: argparse.Namespace) -> None:
    require_secret()
    database = validate_db(args.database)
    if database == "velay" and not args.confirm_maintenance:
        raise BackupError("Confirmer l'arrêt des écritures avec --confirm-maintenance.")
    if args.fixture_evidence and not database.startswith("velay_phase3_"):
        raise BackupError("Les justificatifs fictifs ne sont autorisés qu'avec une base de recette.")
    dest = ensure_backup_destination(args.output_dir)
    filename = "velay-{}-{}.vbackup".format(
        dt.datetime.now(dt.timezone.utc).strftime("%Y%m%dT%H%M%SZ"), secrets.token_hex(4)
    )
    encrypted_file = dest / filename

    # Never stage plaintext on a remote/external encrypted-backup destination.
    with tempfile.TemporaryDirectory(prefix="velay-backup-") as scratch:
        work = Path(scratch)
        work.chmod(0o700)
        database_dump, evidence, manifest_path, package = (
            work / "database.dump", work / "evidence.tar",
            work / "manifest.json", work / "package.tar.gz"
        )
        run_to_file(COMPOSE + [
            "exec", "-T", "database", "pg_dump", "--format=custom", "--no-owner",
            "--no-privileges", "-U", "velay", "-d", database
        ], database_dump)
        if args.fixture_evidence:
            make_fixture_tar(Path(args.fixture_evidence).expanduser().resolve(), evidence)
        else:
            run_to_file(COMPOSE + [
                "exec", "-T", "app", "tar", "-C", "/app/var/evidence", "-cf", "-", "."
            ], evidence)

        manifest = {
            "schema_version": MAGIC_VERSION,
            "source_database": database,
            "created_utc": dt.datetime.now(dt.timezone.utc).isoformat(),
            "files": {
                name: {"sha256": hash_file(file), "bytes": file.stat().st_size}
                for name, file in [("database.dump", database_dump), ("evidence.tar", evidence)]
            },
        }
        manifest_path.write_text(json.dumps(manifest, sort_keys=True, indent=2), encoding="utf-8")
        manifest_path.chmod(0o600)
        with tarfile.open(package, "w:gz") as archive:
            for name, file in [
                ("manifest.json", manifest_path), ("database.dump", database_dump),
                ("evidence.tar", evidence)
            ]:
                archive.add(file, arcname=name, recursive=False)
        package.chmod(0o600)
        try:
            run_to_file(COMPOSE + [
                "exec", "-T", "-e", "VELAY_BACKUP_SECRET", "app",
                "php", "scripts/backup_crypto.php", "encrypt",
            ], encrypted_file, package)
            if encrypted_file.stat().st_size < 80:
                raise BackupError("Sauvegarde chiffrée anormalement petite.")
        except Exception:
            encrypted_file.unlink(missing_ok=True)
            raise
    print("Sauvegarde chiffrée créée :", encrypted_file)


def safe_evidence_paths(path: Path) -> dict[str, str]:
    hashes: dict[str, str] = {}
    with tarfile.open(path, mode="r:") as archive:
        for member in archive:
            if member.isdir() and member.name in {".", "./"}:
                continue
            if not member.isfile() or member.issym() or member.islnk():
                raise BackupError("Archive de justificatifs non sûre.")
            normalized = member.name.removeprefix("./")
            relative = PurePosixPath(normalized)
            if len(relative.parts) != 1 or normalized in {"", ".", ".."} or normalized in hashes:
                raise BackupError("Chemin de justificatif inattendu.")
            stream = archive.extractfile(member)
            if stream is None:
                raise BackupError("Justificatif illisible.")
            digest = hashlib.sha256()
            for chunk in iter(lambda: stream.read(1024 * 1024), b""):
                digest.update(chunk)
            hashes[normalized] = digest.hexdigest()
    return hashes


def unpack_verified(package: Path, work: Path) -> tuple[dict, dict[str, str]]:
    with tarfile.open(package, "r:gz") as archive:
        members = archive.getmembers()
        if {m.name for m in members} != ARCHIVE_MEMBERS or len(members) != len(ARCHIVE_MEMBERS):
            raise BackupError("Archive incomplète ou contenant des fichiers inattendus.")
        if not all(m.isfile() for m in members):
            raise BackupError("Type d'entrée d'archive non autorisé.")
        manifest_stream = archive.extractfile("manifest.json")
        if manifest_stream is None:
            raise BackupError("Manifeste manquant.")
        manifest = json.loads(manifest_stream.read())
        if (manifest.get("schema_version") != MAGIC_VERSION
            or set(manifest.get("files", {})) != {"database.dump", "evidence.tar"}
            or not DB_PATTERN.fullmatch(str(manifest.get("source_database", "")))):
            raise BackupError("Manifeste de sauvegarde invalide.")
        for name in ("database.dump", "evidence.tar"):
            path = work / name
            with archive.extractfile(name) as source, path.open("wb") as output:
                if source is None:
                    raise BackupError("Fichier de sauvegarde manquant.")
                path.chmod(0o600)
                for block in iter(lambda: source.read(1024 * 1024), b""):
                    output.write(block)
            info = manifest["files"][name]
            if info.get("bytes") != path.stat().st_size or info.get("sha256") != hash_file(path):
                raise BackupError("Empreinte SHA-256 invalide : "+name)
    evidence_hashes = safe_evidence_paths(work / "evidence.tar")
    return manifest, evidence_hashes


def validate_archive(input_file: str, mode: str, allow_personal_data: bool) -> None:
    require_secret()
    encrypted = Path(input_file).expanduser().resolve()
    if not encrypted.is_file() or encrypted.is_symlink():
        raise BackupError("Sauvegarde chiffrée introuvable.")

    with tempfile.TemporaryDirectory(prefix="velay-restore-") as scratch:
        work = Path(scratch)
        work.chmod(0o700)
        package = work / "package.tar.gz"
        run_to_file(COMPOSE + [
            "exec", "-T", "-e", "VELAY_BACKUP_SECRET", "app",
            "php", "scripts/backup_crypto.php", "decrypt"
        ], package, encrypted)
        manifest, evidence = unpack_verified(package, work)
        if mode == "verify":
            print("Archive authentifiée :", len(evidence), "justificatif(s), base :", manifest["source_database"])
            return
        if manifest["source_database"] == "velay" and not allow_personal_data:
            raise BackupError("Restauration de données réelles : préciser --confirm-sensitive-data.")

        # Create a unique scratch DB; never drop or reuse a database that existed.
        target = "velay_phase3_restore_" + secrets.token_hex(5)
        if not TEST_RESTORE_PATTERN.fullmatch(target):
            raise BackupError("Nom de restauration refusé.")
        created = False
        try:
            run(COMPOSE + ["exec", "-T", "database", "createdb", "-U", "velay", target])
            created = True
            run_to_file(COMPOSE + [
                "exec", "-T", "database", "pg_restore", "--no-owner",
                "--no-privileges", "-U", "velay", "-d", target
            ], work / "restore.log", work / "database.dump")
            raw = run(COMPOSE + [
                "exec", "-T", "database", "psql", "-U", "velay", "-d", target,
                "-At", "-F", "\t", "-c", "SELECT path, sha256 FROM evidence_file"
            ])
            verified = 0
            for line in raw.decode("utf-8").splitlines():
                path, delimiter, sha = line.partition("\t")
                if not delimiter or evidence.get(path) != sha:
                    raise BackupError("Justificatif restauré absent ou altéré.")
                verified += 1
            print("Restauration isolée validée :", verified, "preuve(s) reliée(s) à PostgreSQL.")
        finally:
            if created:
                run(COMPOSE + ["exec", "-T", "database", "dropdb", "-U", "velay", target])


def selftest() -> None:
    """End-to-end encrypted backup and restore using ONLY synthetic data."""
    previous = os.environ.get("VELAY_BACKUP_SECRET")
    os.environ["VELAY_BACKUP_SECRET"] = secrets.token_urlsafe(40)
    source = "velay_phase3_fixture_" + secrets.token_hex(5)
    created = False
    try:
        with tempfile.TemporaryDirectory(prefix="velay-phase3-selftest-") as scratch:
            directory = Path(scratch)
            directory.chmod(0o700)
            fixture = directory / "fixture"
            fixture.mkdir(mode=0o700)
            pdf = fixture / "synthetic-proof.pdf"
            pdf.write_bytes(b"%PDF-1.4\\nSynthetic non-customer proof\\n%%EOF\\n")
            pdf.chmod(0o600)
            digest = hash_file(pdf)

            run(COMPOSE + ["exec", "-T", "database", "createdb", "-U", "velay", source])
            created = True
            run(COMPOSE + [
                "exec", "-T", "database", "psql", "-U", "velay", "-d", source, "-c",
                "CREATE TABLE evidence_file (path TEXT NOT NULL, sha256 TEXT NOT NULL)"
            ])
            run(COMPOSE + [
                "exec", "-T", "database", "psql", "-U", "velay", "-d", source, "-c",
                "INSERT INTO evidence_file (path, sha256) VALUES ('synthetic-proof.pdf', '" + digest + "')"
            ])
            create(argparse.Namespace(
                output_dir=str(directory),
                database=source,
                fixture_evidence=str(fixture),
                confirm_maintenance=False,
            ))
            archives = list(directory.glob("*.vbackup"))
            if len(archives) != 1:
                raise BackupError("L'auto-test n'a pas créé exactement une archive.")
            validate_archive(str(archives[0]), "verify", False)
            validate_archive(str(archives[0]), "restore-test", False)
            print("Auto-test cryptographique + PostgreSQL + preuve fictive réussi.")
    finally:
        if created:
            run(COMPOSE + ["exec", "-T", "database", "dropdb", "-U", "velay", source])
        if previous is None:
            os.environ.pop("VELAY_BACKUP_SECRET", None)
        else:
            os.environ["VELAY_BACKUP_SECRET"] = previous


def main() -> int:
    parser = argparse.ArgumentParser(description=__doc__)
    sub = parser.add_subparsers(dest="action", required=True)
    creating = sub.add_parser("create", help="backup chiffré PostgreSQL + preuves")
    creating.add_argument("--output-dir", required=True)
    creating.add_argument("--database", default="velay")
    creating.add_argument("--confirm-maintenance", action="store_true")
    creating.add_argument("--fixture-evidence", help="dossier de preuves fictives : DB de recette uniquement")
    verifying = sub.add_parser("verify", help="déchiffrer temporairement et vérifier les empreintes")
    verifying.add_argument("archive")
    restore = sub.add_parser("restore-test", help="restaurer en base éphémère et contrôler les PDF")
    restore.add_argument("archive")
    restore.add_argument("--confirm-sensitive-data", action="store_true")
    sub.add_parser("selftest", help="sauvegarde et restauration avec preuve et base strictement fictives")
    args = parser.parse_args()
    try:
        if args.action == "selftest":
            selftest()
        elif args.action == "create":
            create(args)
        else:
            validate_archive(args.archive, args.action, getattr(args, "confirm_sensitive_data", False))
    except (BackupError, OSError, subprocess.SubprocessError, ValueError, tarfile.TarError) as exc:
        # No passphrase or customer data appears in errors.
        print("Sauvegarde interrompue :", str(exc), file=sys.stderr)
        return 1
    return 0


if __name__ == "__main__":
    sys.exit(main())
