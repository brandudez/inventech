<div id="addUserModal" class="modal">
    <div class="modal-content">
        <span class="close" onclick="closeModal('addUserModal')">&times;</span>

        <h2>Add User</h2>

        <form action="add_user.php" method="POST" class="form-section">

            <input type="email" name="email" placeholder="Email" required>
            <input type="password" name="password" placeholder="Password" required>

            <div id="devices" style="display:none;"></div>
            <button type="submit" class="btn btn-primary">Save User</button>

        </form>
    </div>
</div>