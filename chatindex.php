<!DOCTYPE html>
<html lang="ar">
<head>
    <meta charset="UTF-8">
    <title>Hospital Chatbot</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            direction: rtl;
            text-align: right;
            margin: 30px;
        }
        textarea {
            width: 100%;
            height: 100px;
        }
        #result {
            white-space: pre-wrap;
            border: 1px solid #ccc;
            padding: 15px;
            margin-top: 20px;
        }
        button {
            margin-top: 10px;
            padding: 10px 20px;
        }
    </style>
</head>
<body>
    <h2>الشات بوت الطبي</h2>

    <textarea id="message" placeholder="اكتبي سؤالك عن الدواء..."></textarea>
    <br>
    <button onclick="sendMessage()">إرسال</button>

    <div id="result"></div>

    <script>
        async function sendMessage() {
            const message = document.getElementById("message").value;
            const result = document.getElementById("result");

            result.innerText = "جاري التحميل...";

            const formData = new FormData();
            formData.append("message", message);

            try {
                const response = await fetch("chat_api.php", {
                    method: "POST",
                    body: formData
                });

                const data = await response.json();

                if (data.error) {
                    result.innerText = "Error: " + data.error;
                    return;
                }

                let text = data.answer || "";
                if (data.sources && data.sources.length) {
                    text += "\n\nSources: " + data.sources.join(", ");
                }

                result.innerText = text;
            } catch (err) {
                result.innerText = "Request failed: " + err;
            }
        }
    </script>
</body>
</html>