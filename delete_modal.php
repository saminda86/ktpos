<div class="modal fade" id="deleteConfirmationModal" tabindex="-1" aria-labelledby="deleteConfirmationModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      
      <div class="modal-header">
        <h5 class="modal-title text-danger" id="deleteConfirmationModalLabel"><i class="fas fa-exclamation-triangle"></i> සේවාලාභියා ඉවත් කිරීම</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      
      <div class="modal-body text-center">
        <p class="h5 text-secondary">
            <i class="fas fa-user-minus text-danger me-2"></i> 
            ඔබට සැබවින්ම <b id="clientNamePlaceholder" class="text-dark"></b> ඉවත් කිරීමට අවශ්‍ය බව තහවුරු කරන්න.
        </p>
        <p class="small text-danger mt-3">මෙම ක්‍රියාව ආපසු හැරවිය නොහැක.</p>
      </div>
      
      <div class="modal-footer justify-content-center">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">අවලංගු කරන්න</button>
        <a href="#" id="confirmDeleteLink" class="btn btn-danger">
            <i class="fas fa-trash-alt"></i> ඔව්, ඉවත් කරන්න
        </a>
      </div>
      
    </div>
  </div>
</div>

<script>
/**
 * Delete බොත්තම ක්ලික් කළ විට Modal එක විවෘත කිරීමට ඇති JavaScript
 * @param {number} clientId - ඉවත් කිරීමට ඇති Client ගේ ID අංකය
 * @param {string} clientName - Client ගේ නම
 */
function confirmDelete(clientId, clientName) {
    // Client ගේ නම Modal එකේ පණිවිඩයට යොදයි
    document.getElementById('clientNamePlaceholder').textContent = clientName;
    
    // Confirm Button එකේ Link එක යාවත්කාලීන කරයි
    const deleteLink = document.getElementById('confirmDeleteLink');
    deleteLink.href = 'clients.php?delete_id=' + clientId;
    
    // Modal එක විවෘත කරයි
    const modal = new bootstrap.Modal(document.getElementById('deleteConfirmationModal'));
    modal.show();
}
</script>