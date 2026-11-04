"""
run_local.py - Chay pipeline tren file PDF tren may (khong can Moodle), thay cho benchmark.py.

    cd ai_engine
    python scripts/run_local.py path/to/file.pdf [more.pdf ...] --outputs SUMMARY,QUIZ,FLASHCARDS --out outputs/

Moi file sinh ra <ten>_result.json dung dinh dang contract v1.0 ma Moodle se nhan.
"""

from __future__ import annotations

import argparse
import json
import sys
from pathlib import Path

sys.path.insert(0, str(Path(__file__).resolve().parent.parent))

from app.pipeline import process_pdf  # noqa: E402


def main() -> int:
    parser = argparse.ArgumentParser(description="Run the PDF micro-learning pipeline locally")
    parser.add_argument("pdfs", nargs="+", type=Path)
    parser.add_argument("--outputs", default="SUMMARY,QUIZ")
    parser.add_argument("--out", type=Path, default=Path("outputs"))
    args = parser.parse_args()

    args.out.mkdir(parents=True, exist_ok=True)
    outputs = [o.strip().upper() for o in args.outputs.split(",") if o.strip()]
    failures = 0
    for index, pdf in enumerate(args.pdfs, start=1):
        print(f"[{index}/{len(args.pdfs)}] {pdf.name} ...", flush=True)
        try:
            result = process_pdf(pdf.read_bytes(), task_id=index, doc_id=index, outputs=outputs, title=pdf.stem)
        except Exception as exc:  # noqa: BLE001
            failures += 1
            print(f"    FAILED: {type(exc).__name__}: {exc}")
            continue
        target = args.out / f"{pdf.stem}_result.json"
        target.write_text(json.dumps(result.model_dump(), ensure_ascii=False, indent=2), encoding="utf-8")
        doc = result.document
        print(f"    {doc.total_pages} pages | routing={doc.routing} | ocr={doc.ocr_pages} | vision={doc.vision_pages} "
              f"| chunks={len(result.chunks)} | llm_calls={result.usage['llm_calls']} | {result.processing_time_ms} ms")
        if result.warnings:
            print(f"    warnings: {result.warnings}")
    return 1 if failures else 0


if __name__ == "__main__":
    raise SystemExit(main())

