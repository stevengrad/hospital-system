import ollama

MODEL_NAME = "llama3.2"


def ask_medical_ollama(user_text: str, context: str = "") -> str:
    system_prompt = """
You are a helpful hospital chatbot assistant.

Rules:
- Reply in Egyptian Arabic.
- Keep the answer short and practical.
- Do not give a definite diagnosis.
- Give safe general advice only.
- If the user mentions chest pain, difficulty breathing, fainting, severe bleeding, or severe symptoms, tell them to go to emergency immediately.
- If booking a doctor is useful, suggest booking.
"""

    response = ollama.chat(
        model=MODEL_NAME,
        messages=[
            {"role": "system", "content": system_prompt},
            {
                "role": "user",
                "content": f"Context:\n{context}\n\nUser message:\n{user_text}"
            }
        ]
    )

    return response["message"]["content"]