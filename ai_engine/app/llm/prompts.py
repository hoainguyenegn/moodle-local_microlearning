"""
prompts.py - Prompt templates (giu nguyen cac rang buoc da kiem chung o Process-PDF).

Rang buoc quan trong (xem Bao_Cao_Module_PDF_Processing.md):
- Tra loi CUNG NGON NGU voi tai lieu nguon (language-agnostic).
- Bai tap Toan/Ly khong co loi giai: CAM tu tinh so -> hoi ve phuong phap / cong thuc
  (chong Math Hallucination).
- CAM ky tu backslash trong JSON (LaTeX lam gay JSON).
- Vision prompt nhan CA anh LAN text da trich xuat (Inclusive Routing - Zero Data Loss).

Khac ban cu: Summary + Quiz + Flashcards duoc sinh trong MOT lan goi cho moi chunk
(giam 2-3 lan so request -> tranh Rate Limit 429 cua goi Free).
"""

COMMON_RULES = """CRITICAL INSTRUCTIONS:
- You MUST answer in the EXACT SAME LANGUAGE as the source content.
- Use ONLY information from the source content. Do not invent facts.
- FOR MATH/PHYSICS EXERCISES WITHOUT PROVIDED SOLUTIONS: DO NOT attempt to calculate or solve the numerical problems yourself. Instead, ask about the theory, the formulas or the method of solving.
- If there are math formulas, describe them in words or Unicode symbols. DO NOT use the backslash (\\) character anywhere in the JSON.
- Output MUST be a single valid JSON object and nothing else."""

LESSON_PROMPT = """You are an educational expert building a micro-learning lesson from one section of a lecture document.

{rules}

Produce the following parts:
{parts}

Source content (pages {source_start}-{source_end}):
<<<
{content}
>>>

Return JSON exactly in this format (omit keys that were not requested):
{schema}"""

SUMMARY_PART = "- \"summary\": a short micro-learning lecture: a title, 3-5 key points and the key concepts."
QUIZ_PART = (
    "- \"quiz\": {num_questions} multiple-choice questions. Each question has exactly 4 options "
    "(A, B, C, D), exactly ONE correct answer and a short explanation."
)
FLASHCARD_PART = "- \"flashcards\": up to {num_cards} flashcards (term or question on the front, concise answer on the back)."

SUMMARY_SCHEMA = """  "summary": {"title": "Lesson title", "points": ["Point 1", "Point 2", "Point 3"], "key_concepts": ["concept 1", "concept 2"]}"""
QUIZ_SCHEMA = """  "quiz": [{"question": "Question text?", "options": {"A": "...", "B": "...", "C": "...", "D": "..."}, "correct_answer": "A", "explanation": "Short explanation"}]"""
FLASHCARD_SCHEMA = """  "flashcards": [{"front": "Term", "back": "Definition"}]"""

VISION_PROMPT = """You are an educational expert. This page of a lecture document contains both text and images.
Here is the text already extracted from this page (by PDF parsing or OCR):
---
{text}
---
Look at the attached page image, analyse its visual elements (charts, diagrams, tables, illustrations) and COMBINE them with the extracted text above. Never drop knowledge contained in the extracted text.

{rules}

Return JSON in this format:
{{
  "page_description": "Detailed description combining the text and the visual knowledge",
  "key_points": ["Point 1", "Point 2"],
  "visual_elements": ["Chart 1", "Diagram 2"]
}}"""


def build_lesson_prompt(content: str, source_start: int, source_end: int, outputs: set[str],
                        num_questions: int, num_cards: int) -> str:
    parts, schema = [], []
    if "SUMMARY" in outputs:
        parts.append(SUMMARY_PART)
        schema.append(SUMMARY_SCHEMA)
    if "QUIZ" in outputs:
        parts.append(QUIZ_PART.format(num_questions=num_questions))
        schema.append(QUIZ_SCHEMA)
    if "FLASHCARDS" in outputs:
        parts.append(FLASHCARD_PART.format(num_cards=num_cards))
        schema.append(FLASHCARD_SCHEMA)
    return LESSON_PROMPT.format(
        rules=COMMON_RULES,
        parts="\n".join(parts),
        source_start=source_start,
        source_end=source_end,
        content=content,
        schema="{\n" + ",\n".join(schema) + "\n}",
    )


def build_vision_prompt(text: str) -> str:
    return VISION_PROMPT.format(text=text.strip() or "No text extracted.", rules=COMMON_RULES)

