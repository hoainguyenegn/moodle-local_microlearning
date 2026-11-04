"""
text_chunking.py - Chia noi dung tai lieu thanh cac micro-lesson (chunk) co moc trang nguon.

Khac ban Process-PDF:
- Ban cu chi gom text cua trang TEXT_HEAVY -> trang VISUAL/MIXED bi tach rieng,
  thu tu bai hoc khong con theo thu tu trang.
- Ban moi nhan noi dung da hop nhat cua TUNG trang (text + phan tich Vision),
  giu dung thu tu trang, va tra ve source_start / source_end (1-based, inclusive)
  dung yeu cau trong docs/api-contract.md (PDF markers are inclusive 1-based pages).
"""

from __future__ import annotations

from dataclasses import dataclass

from langchain_text_splitters import RecursiveCharacterTextSplitter

SEPARATORS = ["\n\n", "\n", ". ", "? ", "! ", "; ", ", ", " ", ""]
PAGE_JOINER = "\n\n"


@dataclass
class Chunk:
    position: int          # 1-based, theo thu tu doc
    content: str
    source_start: int      # trang dau (1-based, inclusive)
    source_end: int        # trang cuoi (1-based, inclusive)


def chunk_pages(page_contents: list[tuple[int, str]], chunk_size: int, chunk_overlap: int) -> list[Chunk]:
    """
    page_contents: [(page_number, content), ...] theo thu tu trang.
    Tra ve danh sach Chunk, moi chunk biet minh thuoc trang nao.
    """
    parts: list[str] = []
    boundaries: list[tuple[int, int, int]] = []   # (start, end, page_number) trong chuoi gop
    cursor = 0
    for page_number, content in page_contents:
        content = content.strip()
        if not content:
            continue
        if parts:
            cursor += len(PAGE_JOINER)
        boundaries.append((cursor, cursor + len(content), page_number))
        parts.append(content)
        cursor += len(content)

    if not parts:
        return []

    combined = PAGE_JOINER.join(parts)
    splitter = RecursiveCharacterTextSplitter(
        chunk_size=chunk_size,
        chunk_overlap=chunk_overlap,
        separators=SEPARATORS,
        length_function=len,
        is_separator_regex=False,
        add_start_index=True,     # LangChain tra ve vi tri bat dau -> map ve trang chinh xac
    )

    chunks: list[Chunk] = []
    for document in splitter.create_documents([combined]):
        text = document.page_content
        start = document.metadata.get("start_index", -1)
        if start < 0:                       # Phong ho: tim lai vi tri neu splitter khong tra
            start = max(combined.find(text), 0)
        end = start + len(text)
        pages = [p for (b_start, b_end, p) in boundaries if start < b_end and end > b_start]
        if not pages:
            pages = [boundaries[0][2]]
        chunks.append(Chunk(position=len(chunks) + 1, content=text, source_start=min(pages), source_end=max(pages)))
    return chunks

