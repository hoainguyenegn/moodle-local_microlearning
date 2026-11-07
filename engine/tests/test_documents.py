import io
import json
import zipfile
import pytest
from app.documents import extract, explain


def pptx():
    content = io.BytesIO()
    with zipfile.ZipFile(content, 'w') as archive:
        archive.writestr('ppt/presentation.xml', '<p:presentation xmlns:p="http://schemas.openxmlformats.org/presentationml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><p:sldIdLst><p:sldId r:id="b"/><p:sldId r:id="a"/></p:sldIdLst></p:presentation>')
        archive.writestr('ppt/_rels/presentation.xml.rels', '<Relationships><Relationship Id="a" Target="slides/slide1.xml"/><Relationship Id="b" Target="slides/slide2.xml"/></Relationships>')
        for i in (1, 2):
            archive.writestr(f'ppt/slides/slide{i}.xml', f'<s xmlns:a="http://schemas.openxmlformats.org/drawingml/2006/main"><a:p><a:r><a:t>Content {i}</a:t></a:r></a:p></s>')
        archive.writestr('ppt/slides/_rels/slide2.xml.rels', '<Relationships><Relationship Type="x/notesSlide" Target="../notesSlides/notesSlide2.xml"/></Relationships>')
        archive.writestr('ppt/notesSlides/notesSlide2.xml', '<p:notes xmlns:p="http://schemas.openxmlformats.org/presentationml/2006/main" xmlns:a="http://schemas.openxmlformats.org/drawingml/2006/main"><p:sp><p:ph type="body"/><a:p><a:r><a:t>Teacher notes</a:t></a:r></a:p></p:sp><p:sp><p:ph type="sldNum"/><a:p><a:r><a:t>2</a:t></a:r></a:p></p:sp></p:notes>')
    return content.getvalue()


def pdf(text=None, encrypted=False):
    from pypdf import PdfWriter
    from pypdf.generic import DictionaryObject, NameObject, DecodedStreamObject
    writer = PdfWriter()
    page = writer.add_blank_page(600, 400)
    if text:
        font = DictionaryObject({NameObject('/Type'): NameObject('/Font'), NameObject('/Subtype'): NameObject('/Type1'), NameObject('/BaseFont'): NameObject('/Helvetica')})
        page[NameObject('/Resources')] = DictionaryObject({NameObject('/Font'): DictionaryObject({NameObject('/F1'): writer._add_object(font)})})
        stream = DecodedStreamObject()
        stream.set_data(('BT /F1 12 Tf 50 300 Td ('+text+') Tj ET').encode())
        page[NameObject('/Contents')] = writer._add_object(stream)
    if encrypted:
        writer.encrypt('secret')
    target = io.BytesIO()
    writer.write(target)
    return target.getvalue()


def test_pptx_order_and_teacher_notes():
    result = extract(pptx(), 'pptx')
    assert [s['text'] for s in result['slides']] == ['Content 2', 'Content 1']
    assert result['slides'][0]['notes'] == 'Teacher notes'


def test_pdf_text_empty_and_encrypted():
    assert extract(pdf('Microlearning'), 'pdf')['slides'][0]['text'] == 'Microlearning'
    assert extract(pdf(), 'pdf')['warnings']
    with pytest.raises(ValueError):
        extract(pdf(encrypted=True), 'pdf')


def test_no_external_call_without_consent_or_configuration(monkeypatch):
    def forbidden(*args, **kwargs):
        pytest.fail('Unexpected external API call')
    monkeypatch.setattr('urllib.request.urlopen', forbidden)
    monkeypatch.delenv('GEMINI_API_KEY', raising=False)
    with pytest.raises(ValueError):
        explain([], '', '', False)
    with pytest.raises(ValueError):
        explain([], '', '', True)


def test_ai_contract_and_cost_limits(monkeypatch):
    monkeypatch.setenv('GEMINI_API_KEY', 'test-secret')
    monkeypatch.setenv('GEMINI_MODEL', 'test-model')
    calls = []
    class Response(io.BytesIO):
        pass
    def respond(request, timeout):
        calls.append(json.loads(request.data))
        return Response(json.dumps({'candidates':[{'finishReason':'STOP','content':{'parts':[{'text':json.dumps({'slides':[{'text':'Giải thích được đề xuất.'}],'warnings':['Kiểm tra lại ví dụ.']})}]}}]}).encode())
    monkeypatch.setattr('urllib.request.urlopen', respond)
    result = explain([{'title':'Slide 1','text':'Một ý chính','notes':''}], 'Sinh viên', 'Hiểu ý chính', True)
    assert result['slides'][0]['text']
    assert 'systemInstruction' in calls[0]
    with pytest.raises(ValueError):
        explain([{'title':'x','text':'x'*15001,'notes':''}], '', '', True)
    assert len(calls) == 1
