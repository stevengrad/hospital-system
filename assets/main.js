// assets/js/main.js
document.addEventListener('DOMContentLoaded', function(){
  // small enhancements can go here
});
function startVideo() {
    navigator.mediaDevices.getUserMedia({ video: true })
        .then(stream => {
            const video = document.getElementById('video');
            video.srcObject = stream;
            video.play();  // make sure the video actually plays
        })
        .catch(err => console.error("Camera error:", err));
}
