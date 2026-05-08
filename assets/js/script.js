function openModal() {
    document.getElementById("userModal").style.display = "flex";
}

function backToUser() {
    document.getElementById("adminModal").style.display = "none";
    document.getElementById("userModal").style.display = "flex";
}
function openAdminModal() {
    document.getElementById("userModal").style.display = "none";
    document.getElementById("adminModal").style.display = "flex";
}



// Close when clicking outside
window.onclick = function (e) {
    let userModal = document.getElementById("userModal");
    let adminModal = document.getElementById("adminModal");

    if (e.target === userModal) userModal.style.display = "none";
    if (e.target === adminModal) adminModal.style.display = "none";
}

function closeModal(modalId) {
    document.getElementById(modalId).style.display = "none";
}

