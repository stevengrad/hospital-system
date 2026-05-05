<video id="testVideo" width="300" height="225" autoplay muted playsinline style="border:2px solid green;"></video>
<script>
navigator.mediaDevices.getUserMedia({ video: true })
.then(stream => {
  document.getElementById('testVideo').srcObject = stream;
})
.catch(err => console.error(err));
</script>
