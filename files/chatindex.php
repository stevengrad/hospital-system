<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <title>Hospital Chatbot</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background: #f5f7fb;
            margin: 30px;
            direction: rtl;
            text-align: right;
        }

        .box {
            max-width: 820px;
            margin: auto;
            background: #fff;
            border: 1px solid #ddd;
            border-radius: 18px;
            padding: 22px;
        }

        textarea {
            width: 100%;
            height: 95px;
            border-radius: 12px;
            border: 1px solid #ccc;
            padding: 12px;
            box-sizing: border-box;
            resize: vertical;
        }

        #result {
            white-space: pre-wrap;
            border: 1px solid #ccc;
            border-radius: 12px;
            padding: 15px;
            margin-top: 20px;
            min-height: 120px;
            background: #fafafa;
        }

        button,
        label.upload-btn {
            margin-top: 10px;
            padding: 10px 18px;
            border: 0;
            border-radius: 12px;
            background: #111827;
            color: #fff;
            cursor: pointer;
            display: inline-block;
            font-size: 14px;
        }

        .feedback {
            margin-top: 12px;
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            align-items: center;
        }

        .feedback button {
            background: #0f766e;
            padding: 8px 14px;
        }

        .feedback small {
            color: #555;
        }

        input[type=file] {
            display: none;
        }

        .row {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            align-items: center;
        }

        .recording {
            background: #c0392b !important;
        }

        .hint {
            color: #666;
            font-size: 13px;
            margin-top: 8px;
        }
    </style>
</head>
<body>
<div class="box">
    <h2>الشات بوت الطبي</h2>

    <textarea id="message" placeholder="اكتبي سؤالك عن الدواء، أو ارفعي روشتة PDF/Image، أو سجلي صوت..."></textarea>

    <div class="row">
        <label class="upload-btn" for="attachment">📎 رفع روشتة</label>
        <input id="attachment" type="file" accept="application/pdf,image/*">

        <button id="voiceBtn" type="button">🎙️ تسجيل صوت</button>
        <button id="sendBtn" type="button" onclick="sendMessage()">إرسال</button>
    </div>

    <div class="hint">يتم إرسال الطلب إلى chat_api.php داخل نفس خدمة الويب على AWS، و chat_api.php يوجهه إلى خدمة chatbot على ECS.</div>

    <div id="result"></div>

    <div id="feedback" class="feedback" style="display:none;">
        <button type="button" onclick="sendFeedback('up')">👍 مفيد</button>
        <button type="button" onclick="sendFeedback('down')">👎 غير مفيد</button>
        <small id="feedbackStatus"></small>
    </div>
</div>

<script>
    let mediaRecorder = null;
    let chunks = [];
    let pendingAudioBlob = null;

    let lastUserMessage = "";
    let lastBotReply = "";
    let lastIntent = "";

    const CHAT_ID_KEY = "hospital_chat_id_v2";

    function getStableChatId() {
        let id = localStorage.getItem(CHAT_ID_KEY);
        if (!id) {
            id = "web_" + Date.now() + "_" + Math.random().toString(36).slice(2);
            localStorage.setItem(CHAT_ID_KEY, id);
        }
        return id;
    }

    function getReplyText(data) {
        let text = data.reply || data.answer || data.message || "";

        if (!text && data.data && typeof data.data === "object") {
            text = data.data.reply || data.data.answer || "";
        }

        if (!text) {
            text = JSON.stringify(data, null, 2);
        }

        if (data.sources && Array.isArray(data.sources) && data.sources.length) {
            text += "\n\nSources: " + data.sources.join(", ");
        }

        return text;
    }

    document.getElementById("voiceBtn").addEventListener("click", async function () {
        const btn = this;

        if (mediaRecorder && mediaRecorder.state === "recording") {
            mediaRecorder.stop();
            btn.classList.remove("recording");
            btn.textContent = "🎙️ تسجيل صوت";
            return;
        }

        try {
            const stream = await navigator.mediaDevices.getUserMedia({ audio: true });

            chunks = [];
            mediaRecorder = new MediaRecorder(stream);

            mediaRecorder.ondataavailable = function (event) {
                if (event.data.size > 0) {
                    chunks.push(event.data);
                }
            };

            mediaRecorder.onstop = function () {
                pendingAudioBlob = new Blob(chunks, { type: "audio/webm" });
                stream.getTracks().forEach(track => track.stop());
                document.getElementById("result").innerText = "تم تسجيل الصوت. اضغطي إرسال.";
            };

            mediaRecorder.start();
            btn.classList.add("recording");
            btn.textContent = "⏹️ إيقاف التسجيل";
        } catch (err) {
            document.getElementById("result").innerText = "لم يتم السماح باستخدام الميكروفون.";
        }
    });

    async function sendMessage() {
        const messageInput = document.getElementById("message");
        const attachmentInput = document.getElementById("attachment");
        const result = document.getElementById("result");
        const feedback = document.getElementById("feedback");
        const feedbackStatus = document.getElementById("feedbackStatus");

        const message = messageInput.value;
        const attachment = attachmentInput.files[0];

        if (!message.trim() && !attachment && !pendingAudioBlob) {
            result.innerText = "اكتبي رسالة أو ارفعي ملف أو سجلي صوت.";
            return;
        }

        result.innerText = "جاري التحميل...";
        feedback.style.display = "none";
        feedbackStatus.innerText = "";

        const formData = new FormData();
        formData.append("message", message);
        formData.append("text", message);
        formData.append("chat_id", getStableChatId());

        if (attachment) {
            formData.append("attachment", attachment);
        }

        if (pendingAudioBlob) {
            formData.append("audio", pendingAudioBlob, "voice_message.webm");
        }

        pendingAudioBlob = null;
        attachmentInput.value = "";

        try {
            const response = await fetch("chat_api.php", {
                method: "POST",
                body: formData,
                credentials: "same-origin"
            });

            const data = await response.json();

            if (!response.ok || data.error) {
                result.innerText = "Error: " + (data.error || response.statusText) + (data.raw ? "\n" + data.raw : "");
                return;
            }

            lastUserMessage = message;
            lastBotReply = getReplyText(data);
            lastIntent = data.intent || "";

            result.innerText = lastBotReply;
            feedback.style.display = "flex";
        } catch (err) {
            result.innerText = "Request failed: " + err;
        }
    }

    async function sendFeedback(rating) {
        const status = document.getElementById("feedbackStatus");

        if (!lastBotReply) {
            status.innerText = "لا يوجد رد لتقييمه.";
            return;
        }

        let comment = "";
        if (rating === "down") {
            comment = prompt("ممكن تكتبي سبب بسيط عشان نحسن الرد؟") || "";
        }

        status.innerText = "جاري حفظ التقييم...";

        const formData = new FormData();
        formData.append("feedback_action", "1");
        formData.append("chat_id", getStableChatId());
        formData.append("user_message", lastUserMessage);
        formData.append("bot_reply", lastBotReply);
        formData.append("rating", rating);
        formData.append("comment", comment);
        formData.append("intent", lastIntent);

        try {
            const response = await fetch("chat_api.php", {
                method: "POST",
                body: formData,
                credentials: "same-origin"
            });

            const data = await response.json();

            status.innerText = (data.ok || data.saved || data.status === "ok")
                ? "تم حفظ التقييم ✅"
                : "لم يتم حفظ التقييم";
        } catch (err) {
            status.innerText = "حصل خطأ أثناء حفظ التقييم";
        }
    }
</script>
</body>
</html>