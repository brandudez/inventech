function filterType(type) {
    const search = document.getElementById("searchInput").value;
    window.location.href = "?type=" + type + "&search=" + search;
}

document.getElementById("searchInput").addEventListener("keyup", function () {
    const value = this.value;
    const urlParams = new URLSearchParams(window.location.search);
    const type = urlParams.get('type') || '';

    clearTimeout(this.timer);
    this.timer = setTimeout(() => {
        window.location.href = "?search=" + value + "&type=" + type;
    }, 500);
});