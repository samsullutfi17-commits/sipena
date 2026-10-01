#!/usr/bin/env python3
import json
import os
import sys

from openai import OpenAI

GROQ_API_KEY = os.environ.get("GROQ_API_KEY")

if not GROQ_API_KEY:
    print(json.dumps({"error": "GROQ_API_KEY tidak ditemukan di environment secrets."}))
    sys.exit(1)

client = OpenAI(
    api_key=GROQ_API_KEY,
    base_url="https://api.groq.com/openai/v1"
)

JENIS_LABELS = {
    'pg':          'Pilihan Ganda',
    'multiple':    'PG Kompleks',
    'tf':          'Benar/Salah',
    'short':       'Jawaban Singkat',
    'matching':    'Menjodohkan',
    'ordering':    'Penyusunan Urutan',
    'esai':        'Esai',
    'studi_kasus': 'Studi Kasus',
}

KOGNITIF_LABELS = {
    'C1': 'C1 – Mengingat',
    'C2': 'C2 – Memahami',
    'C3': 'C3 – Menerapkan',
    'C4': 'C4 – Menganalisis',
    'C5': 'C5 – Mengevaluasi',
    'C6': 'C6 – Mencipta',
}


def generate_blueprint(topic: str, total: int,
                        mudah: int, sedang: int, sulit: int,
                        jenis_list: list, kognitif_list: list,
                        poin_default: int, mata_kuliah: str) -> list:

    jenis_desc = ', '.join(
        f'{j} ({JENIS_LABELS.get(j, j)})' for j in jenis_list
    )
    kognitif_desc = ', '.join(
        f'{k} ({KOGNITIF_LABELS.get(k, k)})' for k in kognitif_list
    )
    jenis_valid = ', '.join(jenis_list)
    kognitif_valid = ', '.join(kognitif_list)

    prompt = f"""Buatkan kisi-kisi soal ujian tentang topik "{topic}" untuk mata kuliah "{mata_kuliah}".

Ketentuan wajib:
- Total baris kisi-kisi: tepat {total}
- Distribusi tingkat kesulitan: {mudah} soal mudah, {sedang} soal sedang, {sulit} soal sulit
- Jenis soal yang digunakan: {jenis_desc}
- Level kognitif Bloom yang digunakan: {kognitif_desc}
- Distribusikan jenis soal dan level kognitif secara merata sesuai jumlah
- Poin default per soal: {poin_default}

Untuk setiap baris kisi-kisi berikan field:
- "cpmk": Capaian Pembelajaran Mata Kuliah spesifik yang diukur (kalimat lengkap, contoh: "Mahasiswa mampu menjelaskan konsep X")
- "indikator": Indikator terukur yang menunjukkan tercapainya CPMK tersebut (kalimat operasional, contoh: "Mendefinisikan X dengan benar")
- "level_kognitif": salah satu dari: {kognitif_valid}
- "tingkat_kesulitan": salah satu dari: mudah, sedang, sulit
- "jenis_soal": salah satu dari: {jenis_valid}
- "poin": integer ({poin_default} default, soal sulit bisa lebih tinggi)

Output hanya JSON:
{{
  "kisi_kisi": [
    {{
      "cpmk": "...",
      "indikator": "...",
      "level_kognitif": "C1",
      "tingkat_kesulitan": "mudah",
      "jenis_soal": "pg",
      "poin": {poin_default}
    }}
  ]
}}

PASTIKAN:
1. Tepat {total} baris
2. Distribusi kesulitan tepat: {mudah} mudah, {sedang} sedang, {sulit} sulit
3. Setiap jenis_soal harus dari: {jenis_valid}
4. Setiap level_kognitif harus dari: {kognitif_valid}
5. Berikan hanya JSON tanpa markdown atau penjelasan"""

    try:
        response = client.chat.completions.create(
            model="llama-3.3-70b-versatile",
            messages=[
                {
                    "role": "system",
                    "content": (
                        "Kamu adalah ahli penyusunan kisi-kisi soal akademik perguruan tinggi Indonesia. "
                        "Selalu berikan output JSON valid sesuai format yang diminta."
                    )
                },
                {"role": "user", "content": prompt}
            ],
            response_format={"type": "json_object"}
        )

        content = response.choices[0].message.content or "{}"
        result = json.loads(content)

        # Try common wrapper keys
        for key in ('kisi_kisi', 'data', 'items', 'soal', 'blueprint', 'result'):
            if key in result and isinstance(result[key], list):
                return result[key]

        if isinstance(result, list):
            return result

        return []

    except Exception as e:
        return [{"error": str(e)}]


if __name__ == "__main__":
    args = sys.argv[1:]
    if len(args) < 2:
        print(json.dumps({"error": "Usage: python ai_kisi_kisi.py <topic> <total> [mudah] [sedang] [sulit] [jenis] [kognitif] [poin] [mata_kuliah]"}))
        sys.exit(1)

    topic       = args[0]
    total       = int(args[1])
    mudah       = int(args[2])   if len(args) > 2 else max(1, total // 4)
    sedang      = int(args[3])   if len(args) > 3 else max(1, total // 2)
    sulit       = int(args[4])   if len(args) > 4 else total - mudah - sedang
    jenis_raw   = args[5]        if len(args) > 5 else 'pg'
    kognitif_raw= args[6]        if len(args) > 6 else 'C1,C2,C3'
    poin_default= int(args[7])   if len(args) > 7 else 4
    mata_kuliah = args[8]        if len(args) > 8 else topic

    jenis_list   = [j.strip() for j in jenis_raw.split(',')    if j.strip()]
    kognitif_list= [k.strip() for k in kognitif_raw.split(',') if k.strip()]

    result = generate_blueprint(topic, total, mudah, sedang, sulit,
                                 jenis_list, kognitif_list, poin_default, mata_kuliah)
    print(json.dumps(result, ensure_ascii=False))
