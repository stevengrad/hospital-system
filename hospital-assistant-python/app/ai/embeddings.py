from sentence_transformers import SentenceTransformer

# Multilingual pretrained model: Arabic + English + mixed text
model = SentenceTransformer("paraphrase-multilingual-MiniLM-L12-v2")

def get_embedding(text: str):
    return model.encode(text)