"""
vision_cv.py - Tien xu ly anh bang OpenCV truoc khi gui Vision LLM (port tu Process-PDF).

1. Do do mo (Laplacian variance).
2. Khu nhieu (Non-Local Means) neu anh mo.
3. Tang tuong phan (CLAHE tren kenh L cua khong gian mau LAB).
"""

from __future__ import annotations

import cv2
import numpy as np


def _decode(image_bytes: bytes) -> np.ndarray:
    img = cv2.imdecode(np.frombuffer(image_bytes, np.uint8), cv2.IMREAD_COLOR)
    if img is None:
        raise ValueError("cannot decode image")
    return img


def _encode(img: np.ndarray) -> bytes:
    ok, encoded = cv2.imencode(".png", img)
    if not ok:
        raise ValueError("cannot encode image")
    return encoded.tobytes()


def measure_blur(img: np.ndarray) -> float:
    """Laplacian variance: < 50 rat mo, 50-200 trung binh, > 200 ro net."""
    gray = cv2.cvtColor(img, cv2.COLOR_BGR2GRAY)
    return round(float(cv2.Laplacian(gray, cv2.CV_64F).var()), 2)


def _enhance_contrast(img: np.ndarray) -> np.ndarray:
    l_channel, a_channel, b_channel = cv2.split(cv2.cvtColor(img, cv2.COLOR_BGR2LAB))
    clahe = cv2.createCLAHE(clipLimit=2.0, tileGridSize=(8, 8))
    merged = cv2.merge([clahe.apply(l_channel), a_channel, b_channel])
    return cv2.cvtColor(merged, cv2.COLOR_LAB2BGR)


def preprocess_for_vision(image_bytes: bytes, blur_threshold: float = 50.0) -> dict:
    """
    Tra ve {processed_image, blur_score, is_blurry, steps_applied}.
    Neu anh khong decode duoc thi tra nguyen anh goc (khong lam hong pipeline).
    """
    try:
        img = _decode(image_bytes)
    except ValueError:
        return {"processed_image": image_bytes, "blur_score": None, "is_blurry": False, "steps_applied": []}

    blur_score = measure_blur(img)
    is_blurry = blur_score < blur_threshold
    steps: list[str] = []
    if is_blurry:
        img = cv2.fastNlMeansDenoisingColored(img, None, 12, 12, 7, 21)
        steps.append("denoise")
    img = _enhance_contrast(img)
    steps.append("enhance_contrast")
    return {"processed_image": _encode(img), "blur_score": blur_score, "is_blurry": is_blurry, "steps_applied": steps}

