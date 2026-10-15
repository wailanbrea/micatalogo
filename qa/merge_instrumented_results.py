"""Combine explicit reruns of the SAME candidate, never count omissions as PASS.

The first report is the full manifest. Later reports must contain only its test
identities. The most recent real outcome wins, including regressions/failures.
Build identity must be verified by the caller before combining configurations.
"""
import argparse
import hashlib
import json
from collections import Counter
from pathlib import Path


def main():
    parser = argparse.ArgumentParser()
    parser.add_argument("--output", type=Path, required=True)
    parser.add_argument("reports", type=Path, nargs="+")
    args = parser.parse_args()
    if args.output.exists():
        parser.error("Output already exists; preserve the earlier evidence.")
    outcomes = {}
    sources = []
    expected = None
    for path in args.reports:
        raw = path.read_bytes()
        report = json.loads(raw)
        results = {test["test"]: test["state"] for test in report["tests"]}
        if expected is None:
            expected = set(results)
            if len(expected) != report["declared"] or report["missing"] or report["unexpected"]:
                parser.error("First report must account for the full manifest.")
        if not set(results).issubset(expected) or report["missing"] or report["unexpected"]:
            parser.error("Rerun has missing or unexpected test identities.")
        outcomes.update(results)
        sources.append({"file": path.name, "sha256": hashlib.sha256(raw).hexdigest(),
                        "counts": report["counts"], "gate": report["gate"]})
    counts = Counter(outcomes.values())
    approved = len(outcomes) == len(expected) and counts["PASS"] == len(expected)
    result = {"candidate": "1.0.112 / 113", "method": "Latest outcome per identity across isolated fixture configurations",
              "declared": len(expected), "unique_test_outcomes": len(outcomes),
              "counts": dict(counts), "gate": "PASS" if approved else "NOT_APPROVED",
              "sources": sources, "tests": [{"test": name, "state": state} for name, state in sorted(outcomes.items())]}
    args.output.write_text(json.dumps(result, ensure_ascii=False, indent=2) + "\n", encoding="utf-8")
    print(json.dumps({key: result[key] for key in ("declared", "unique_test_outcomes", "counts", "gate")}))
    raise SystemExit(0 if approved else 1)


if __name__ == "__main__":
    main()
