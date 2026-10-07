// Modal logic for VPS Manager
const modal = document.getElementById('editModal');
const closeModal = document.querySelector('.close-modal');
const editForm = document.getElementById('editForm');
const vpsList = window.vpsList || [];
if (modal && editForm) {
    document.addEventListener('click', function(e) {
        if (e.target.classList.contains('edit-btn')) {
            const idx = e.target.getAttribute('data-index');
            const vps = vpsList[idx];
            document.getElementById('edit_id').value = idx;
            document.getElementById('edit_ip').value = vps.ip || '';
            document.getElementById('edit_username').value = vps.username || '';
            document.getElementById('edit_password').value = vps.password || '';
            document.getElementById('edit_info').value = vps.info || '';
            document.getElementById('edit_aapanel_keyapi').value = vps.aapanel_keyapi || '';
            document.getElementById('edit_status').value = vps.status || '';
            modal.style.display = 'block';
        }
    });
    closeModal.onclick = function() { modal.style.display = 'none'; };
}
// Add VPS modal logic
const addModal = document.getElementById('addModal');
const openAddModal = document.getElementById('openAddModal');
const closeAddModal = document.querySelector('.close-modal-add');
if (addModal && openAddModal && closeAddModal) {
    openAddModal.onclick = function() { addModal.style.display = 'block'; setTimeout(() => document.getElementById('add_ip').focus(), 200); };
    closeAddModal.onclick = function() { addModal.style.display = 'none'; };
}
// Quick Add modal logic
const quickAddModal = document.getElementById('quickAddModal');
const openQuickAddModal = document.getElementById('openQuickAddModal');
const closeQuickAddModal = document.querySelector('.close-modal-quick');
if (quickAddModal && openQuickAddModal && closeQuickAddModal) {
    openQuickAddModal.onclick = function() { quickAddModal.style.display = 'block'; setTimeout(() => document.getElementById('quick_vps_list').focus(), 200); };
    closeQuickAddModal.onclick = function() { quickAddModal.style.display = 'none'; };
}
window.onclick = function(e) {
    if (e.target === modal) modal.style.display = 'none';
    if (e.target === addModal) addModal.style.display = 'none';
    if (e.target === quickAddModal) quickAddModal.style.display = 'none';
};
