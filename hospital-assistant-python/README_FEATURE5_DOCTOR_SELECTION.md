# Feature 5 - Doctor selection before appointment slots

## What changed

The booking flow is now more user-friendly:

Old flow:

Symptoms → Specialty → Branch → mixed appointment slots from all doctors

New flow:

Symptoms → Specialty → Branch → choose doctor → show slots for selected doctor → book slot

## Updated files

- `app/tools/chat_free.py`
- `app/templates/index.html`

## Example

User:

```text
عندي صداع
```

Bot asks safety triage, then branch.

User:

```text
فرع 1
```

Bot:

```text
في Branch A - Main Hospital، قسم Neurology عندنا فيه الدكاترة دول:
1) دكتور Amal Ibrahim
2) دكتور ...

اختاري دكتور برقم. مثال: دكتور 1
```

User:

```text
دكتور 1
```

Bot shows only this doctor's available slots.

User:

```text
احجز 2
```

Bot completes the booking as before.

## UI update

The test page now renders doctor buttons when the backend returns `intent = choose_doctor` and `data.doctors`.
Clicking a doctor button sends:

```text
دكتور رقم 1
```

Then the backend returns slots for that selected doctor.
