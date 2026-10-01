#!/usr/bin/env python3
import json
import os
import sys

from openai import OpenAI

GROQ_API_KEY = os.environ.get("GROQ_API_KEY")

if not GROQ_API_KEY:
    print(json.dumps([{"error": "GROQ_API_KEY tidak ditemukan di environment secrets."}]))
    sys.exit(1)

client = OpenAI(
    api_key=GROQ_API_KEY,
    base_url="https://api.groq.com/openai/v1"
)

def generate_questions(topic: str, jenis: str, count: int = 5,
                        kesulitan: str = 'sedang', kognitif: str = 'C1',
                        cpmk: str = '', poin: int = 4) -> list:

    jenis_labels = {
        'pg':       'Pilihan Ganda dengan 5 opsi (A-E), tentukan mana yang benar',
        'tf':       'Benar/Salah',
        'short':    'Jawaban Singkat dengan keyword jawaban',
        'multiple': 'Pilihan Ganda Kompleks dengan beberapa jawaban benar',
        'matching': 'Menjodohkan (pasangkan Kolom A dengan Kolom B)',
        'ordering': 'Penyusunan Urutan (urutkan langkah/item dari atas ke bawah)',
        'esai':        'Esai dengan kriteria penilaian',
        'studi_kasus': 'Studi Kasus (skenario/kasus nyata diikuti pertanyaan analisis, jawaban model/rubrik penilaian)'
    }

    kesulitan_labels = {
        'mudah':  'mudah (konsep dasar, hafalan, pemahaman sederhana)',
        'sedang': 'sedang (pemahaman mendalam, aplikasi konsep)',
        'sulit':  'sulit (analisis, evaluasi, sintesis, kasus kompleks)'
    }

    kognitif_labels = {
        'C1': 'C1 - Mengingat (recall fakta, definisi, konsep dasar)',
        'C2': 'C2 - Memahami (menjelaskan, mengklasifikasi, merangkum)',
        'C3': 'C3 - Menerapkan (menggunakan konsep dalam situasi baru)',
        'C4': 'C4 - Menganalisis (membedah, membandingkan, menghubungkan)',
        'C5': 'C5 - Mengevaluasi (menilai, mengkritik, memutuskan)',
        'C6': 'C6 - Mencipta (merancang, menghasilkan solusi baru)'
    }

    # Support multiple jenis (comma-separated)
    jenis_list = [j.strip() for j in jenis.split(',') if j.strip() and j.strip() in jenis_labels]
    if not jenis_list:
        jenis_list = ['pg']

    # Support multiple kesulitan (comma-separated)
    kesulitan_list = [k.strip() for k in kesulitan.split(',') if k.strip() and k.strip() in kesulitan_labels]
    if not kesulitan_list:
        kesulitan_list = ['sedang']
    kesulitan_desc = ' / '.join([kesulitan_labels[k] for k in kesulitan_list])

    if len(jenis_list) == 1:
        jenis_desc = jenis_labels[jenis_list[0]]
        jenis_default = jenis_list[0]
        jenis_distribution_note = f'Semua soal bertipe: {jenis_desc}'
    else:
        jenis_descs = [f'{j} ({jenis_labels[j]})' for j in jenis_list]
        jenis_desc = ', '.join(jenis_descs)
        jenis_default = jenis_list[0]
        jenis_distribution_note = (
            f'Distribusikan {count} soal secara merata di antara jenis soal berikut: {", ".join(jenis_list)}. '
            f'Setiap soal HARUS mengisi field "jenis_soal" dengan salah satu dari: {", ".join(jenis_list)}.'
        )

    # Support multiple cognitive levels (comma-separated)
    kognitif_list  = [k.strip() for k in kognitif.split(',') if k.strip()]
    if not kognitif_list:
        kognitif_list = ['C1']
    kognitif_descs = [kognitif_labels.get(k, k) for k in kognitif_list]

    if len(kognitif_list) == 1:
        kognitif_context = f'- Level kognitif Taksonomi Bloom: {kognitif_descs[0]}'
        kognitif_note    = f'Pastikan setiap soal mencerminkan tingkat kesulitan "{kesulitan}" dan level kognitif "{kognitif_list[0]}" secara konsisten.'
        kognitif_default = kognitif_list[0]
    else:
        levels_str       = '\n  - '.join(kognitif_descs)
        kognitif_context = f'- Level kognitif Taksonomi Bloom (distribusikan soal secara merata pada level berikut):\n  - {levels_str}'
        kognitif_note    = (f'Distribusikan {count} soal secara merata di antara level kognitif: {", ".join(kognitif_list)}. '
                            f'Setiap soal harus mencerminkan level kognitifnya masing-masing di field "level_kognitif".')
        kognitif_default = kognitif_list[0]

    cpmk_context = f'\nTujuan Pembelajaran / CPMK: "{cpmk}"' if cpmk else ''

    prompt = f"""Buatkan {count} soal ujian tentang "{topic}" dengan ketentuan berikut:
- Jenis soal: {jenis_desc}
- Tingkat kesulitan: {kesulitan_desc}
{kognitif_context}{cpmk_context}

{jenis_distribution_note}
{kognitif_note}

PENTING: Berikan output sebagai JSON object dengan key "soal" yang berisi ARRAY soal.
SELALU gunakan array, bahkan jika hanya 1 soal. Contoh format:

{{
  "soal": [
    {{
      "pertanyaan": "Teks pertanyaan",
      "jenis_soal": "{jenis_default}",
      "poin": {poin},
      "tingkat_kesulitan": "{kesulitan_list[0]}",
      "level_kognitif": "{kognitif_default}",
      "cpmk": "{cpmk}",
      "opsi": [],
      "pairs": {{}},
      "correct_order": [],
      "pembahasan": "Penjelasan mengapa jawaban tersebut benar"
    }}
  ]
}}

Aturan per jenis soal:

Pilihan Ganda (pg):
- "opsi": 5 item [{{"teks": "Opsi A", "benar": false}}, ...], tepat satu benar
- "pairs": {{}}, "correct_order": []

Benar/Salah (tf):
- "opsi": [{{"teks": "BENAR", "benar": true/false}}, {{"teks": "SALAH", "benar": true/false}}]
- "pairs": {{}}, "correct_order": []

Jawaban Singkat (short):
- "opsi": [{{"teks": "keyword1", "benar": true}}, {{"teks": "keyword2", "benar": true}}]
- "pairs": {{}}, "correct_order": []

Pilihan Ganda Kompleks (multiple):
- "opsi": beberapa item, lebih dari satu bisa benar
- "pairs": {{}}, "correct_order": []

Menjodohkan (matching):
- "opsi": []
- "pairs": object berisi pasangan kunci-nilai, minimal 4 pasang, contoh: {{"Beneficence": "Berbuat baik", "Non-maleficence": "Tidak merugikan"}}
- "correct_order": []

Penyusunan Urutan (ordering):
- "opsi": []
- "pairs": {{}}
- "correct_order": array berisi item dalam URUTAN YANG BENAR dari pertama ke terakhir, minimal 4 item

Esai:
- "opsi": [], "pairs": {{}}, "correct_order": []
- "pembahasan" berisi kriteria penilaian lengkap

Studi Kasus (studi_kasus):
- "pertanyaan" berisi skenario/narasi kasus diikuti pertanyaan analisis (gabungkan dalam satu teks)
- "opsi": [], "pairs": {{}}, "correct_order": []
- "pembahasan" berisi jawaban model dan rubrik penilaian lengkap

Pastikan soal berkualitas tinggi, sesuai konteks akademik, dan relevan dengan topik "{topic}".
Berikan hanya JSON array tanpa markdown atau penjelasan tambahan."""

    try:
        response = client.chat.completions.create(
            model="llama-3.3-70b-versatile",
            messages=[
                {"role": "system", "content": "Kamu adalah pembuat soal ujian profesional untuk perguruan tinggi. Selalu berikan output dalam format JSON yang valid sesuai instruksi."},
                {"role": "user", "content": prompt}
            ],
            response_format={"type": "json_object"}
        )

        content = response.choices[0].message.content or "{}"
        result = json.loads(content)

        def wrap_to_list(val):
            """Ensure val is always a list of soal dicts."""
            if isinstance(val, list):
                return val
            if isinstance(val, dict):
                # Single soal object — wrap it
                return [val]
            return []

        # Try common wrapper keys first
        for key in ('soal', 'soal_list', 'questions', 'question', 'data', 'items', 'result'):
            if key in result:
                candidate = wrap_to_list(result[key])
                if candidate:
                    return candidate

        # Root-level list
        if isinstance(result, list):
            return result

        # Root dict might BE a single soal (has 'pertanyaan' key)
        if 'pertanyaan' in result:
            return [result]

        # Last resort: find first list-valued key
        for key in result:
            if isinstance(result[key], list) and result[key]:
                return result[key]

        # Give up — return the whole dict as a single soal
        return [result]

    except Exception as e:
        return [{"error": str(e)}]


if __name__ == "__main__":
    if len(sys.argv) < 3:
        print(json.dumps({"error": "Usage: python ai_generate.py <topic> <jenis> [count] [kesulitan] [kognitif] [cpmk] [poin]"}))
        sys.exit(1)

    topic     = sys.argv[1]
    jenis     = sys.argv[2]
    count     = int(sys.argv[3])   if len(sys.argv) > 3 else 5
    kesulitan = sys.argv[4]        if len(sys.argv) > 4 else 'sedang'
    kognitif  = sys.argv[5]        if len(sys.argv) > 5 else 'C1'
    cpmk      = sys.argv[6]        if len(sys.argv) > 6 else ''
    poin      = int(sys.argv[7])   if len(sys.argv) > 7 else 4

    result = generate_questions(topic, jenis, count, kesulitan, kognitif, cpmk, poin)
    print(json.dumps(result, ensure_ascii=False))
