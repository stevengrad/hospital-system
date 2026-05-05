async function loadModels() {
    try {
        const MODEL_URL = 'models'; // local folder
        await faceapi.nets.tinyFaceDetector.loadFromUri(MODEL_URL);
        await faceapi.nets.faceRecognitionNet.loadFromUri(MODEL_URL);
        await faceapi.nets.faceLandmark68Net.loadFromUri(MODEL_URL);
        startVideo();
    } catch (err) {
        console.error("Model loading error:", err);
        alert("Failed to load face-api models. Check your internet connection.");
    }
}
