/* Playback stays local; text is rendered and escaped by Moodle. */
document.addEventListener('click', function(event) {
    const button = event.target.closest('.speech-seek');
    if (!button) { return; }
    const audio = document.getElementById(button.dataset.audio);
    audio.currentTime = Number(button.dataset.start);
    audio.play().catch(function() { /* Native controls remain available. */ });
});
document.querySelectorAll('audio[id^="audio-"]').forEach(function(audio) {
    audio.addEventListener('timeupdate', function() {
        document.querySelectorAll('.speech-slide').forEach(function(slide) {
            if (slide.dataset.audio === audio.id) {
                const active = audio.currentTime >= Number(slide.dataset.start) &&
                    audio.currentTime < Number(slide.dataset.end);
                slide.classList.toggle('alert-info', active);
            }
        });
    });
});
