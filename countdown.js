function startCountdown(duration, display) {
    let timer = duration, minutes, seconds;
    const interval = setInterval(function () {
        minutes = parseInt(timer / 60, 10);
        seconds = parseInt(timer % 60, 10);

        minutes = minutes < 10 ? "0" + minutes : minutes;
        seconds = seconds < 10 ? "0" + seconds : seconds;

        display.textContent = `You are working at ${display.dataset.location} for another ${minutes}:${seconds}`;

        if (--timer < 0) {
            clearInterval(interval);
            display.textContent = '';
        }
    }, 1000);
}