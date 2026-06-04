# Feature 2 - Conversation Memory

This feature adds lightweight conversation memory for the chatbot.

## What it remembers

The chatbot now stores, per `chat_id`:

- last intent
- last topic
- last medications mentioned
- last specialty
- last doctor
- last branch
- short recent history

Memory is stored in `.chat_memory/` as JSON files. The chatbot still has the old booking state in `.chat_state/`; this feature adds a general memory layer for follow-up questions.

## Why it matters

The user can now ask contextual follow-ups such as:

```text
User: مخاطر Aspirin
Bot: ...
User: ومع Brufen ينفع؟
```

The system enriches the second message internally to include the previous topic.

Another example:

```text
User: عندي صداع
Bot: asks triage / suggests Neurology
User: ده في أنهي فرع؟
```

The system has a better chance to understand that `ده` refers to the last specialty/topic.

## Important frontend note

Memory works best when the frontend sends the same `chat_id` for all messages in the same conversation.

If `chat_id` is not sent, the backend falls back to:

1. `username`
2. `patient_id`
3. `default`

So the best request body is:

```json
{
  "chat_id": "user-123-session-1",
  "username": "donia",
  "text": "مخاطر Aspirin"
}
```

## Reset memory

The user can reset using:

```text
reset
ابدأ من جديد
clear memory
امسح الذاكرة
```

There is also an API endpoint:

```http
POST /chat/memory/reset
```

Body:

```json
{
  "chat_id": "user-123-session-1"
}
```

## Debug memory

```http
GET /chat/memory?chat_id=user-123-session-1
```

This returns a short summary of remembered context, not the full hidden chat state.
