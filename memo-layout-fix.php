<?php
// Add this to your existing memo.php file to fix the layout

// After your existing memo fetching code, add data attributes to memo cards
?>

<!-- Replace your existing memo display section with this improved version -->
<div class="memo-tabs">
    <button class="memo-tab" data-tab="all">All Memos</button>
    <?php if ($user['role'] == 'admin'): ?>
        <button class="memo-tab" data-tab="recent">Recent</button>
        <button class="memo-tab" data-tab="manager">Manager</button>
        <button class="memo-tab" data-tab="supervisor">Supervisor</button>
        <button class="memo-tab" data-tab="agent">Agent</button>
    <?php endif; ?>
</div>

<?php if (empty($memos)): ?>
    <div class="empty-state">
        <div class="empty-state-icon">
            <i class="fas fa-file-alt"></i>
        </div>
        <h3 class="empty-state-title">No memos found</h3>
        <p class="empty-state-text">
            <?php if ($user['role'] == 'admin'): ?>
                Create your first memo by clicking the "Create New Memo" button above.
            <?php else: ?>
                No memos have been shared with <?php echo $user['role']; ?>s yet.
            <?php endif; ?>
        </p>
    </div>
<?php else: ?>
    <div class="memo-grid">
        <?php foreach ($memos as $memo): ?>
            <div class="memo-card" 
                 data-memo-id="<?php echo $memo['memo_id']; ?>"
                 data-visible-roles="<?php echo htmlspecialchars($memo['visible_roles'] ?? ''); ?>"
                 data-created-date="<?php echo $memo['created_at']; ?>">
                
                <?php if (!empty($memo['images'])): ?>
                    <div class="memo-image" onclick="openImageModal(<?php echo $memo['memo_id']; ?>, 0)">
                        <img src="<?php echo htmlspecialchars($memo['images'][0]['image_path']); ?>" alt="Memo image">
                        <?php if (count($memo['images']) > 1): ?>
                            <div class="memo-image-count">
                                +<?php echo count($memo['images']) - 1; ?> more
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
                
                <div class="memo-content">
                    <h3 class="memo-title"><?php echo htmlspecialchars($memo['title']); ?></h3>
                    <div class="memo-text"><?php echo nl2br(htmlspecialchars($memo['content'])); ?></div>
                    
                    <div class="memo-meta">
                        <div class="memo-author">
                            <div class="memo-author-avatar">
                                <?php echo strtoupper(substr($memo['creator_name'], 0, 1)); ?>
                            </div>
                            <span><?php echo htmlspecialchars($memo['creator_name']); ?></span>
                        </div>
                        <div class="memo-date">
                            <?php echo date('M j, Y', strtotime($memo['created_at'])); ?>
                        </div>
                    </div>
                </div>
                
                <?php if ($user['role'] == 'admin'): ?>
                    <div class="memo-footer">
                        <div class="memo-visibility">
                            <?php 
                            if (isset($memo['visible_roles'])) {
                                $roles = explode(',', $memo['visible_roles']);
                                foreach ($roles as $role): 
                                    if (!empty(trim($role))):
                            ?>
                                <span class="role-badge <?php echo trim($role); ?>">
                                    <i class="fas fa-<?php echo trim($role) == 'manager' ? 'user-tie' : (trim($role) == 'supervisor' ? 'user-shield' : 'user'); ?>"></i>
                                    <?php echo ucfirst(trim($role)); ?>
                                </span>
                            <?php 
                                    endif;
                                endforeach; 
                            }
                            ?>
                        </div>
                        
                        <div class="memo-actions">
                            <button class="btn-icon edit" title="Edit">
                                <i class="fas fa-edit"></i>
                            </button>
                            <a href="?delete_memo=<?php echo $memo['memo_id']; ?>" 
                               class="btn-icon delete" 
                               title="Delete" 
                               onclick="return confirm('Are you sure you want to delete this memo?')">
                                <i class="fas fa-trash-alt"></i>
                            </a>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<!-- Add these modals to your page -->
<!-- Create Memo Modal -->
<div id="createMemoModal" class="modal-overlay">
    <div class="modal">
        <div class="modal-header">
            <h3 class="modal-title">Create New Memo</h3>
            <button type="button" class="modal-close">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <div class="modal-body">
            <form action="" method="POST" enctype="multipart/form-data">
                <div class="form-group">
                    <label for="title" class="form-label">Memo Title</label>
                    <input type="text" id="title" name="title" class="form-control" required>
                </div>
                
                <div class="form-group">
                    <label for="content" class="form-label">Memo Content</label>
                    <textarea id="content" name="content" class="form-control" required></textarea>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Visible to:</label>
                    <div style="display: flex; gap: 1rem; flex-wrap: wrap;">
                        <label style="display: flex; align-items: center; gap: 0.5rem;">
                            <input type="checkbox" name="visible_to[]" value="manager">
                            Managers
                        </label>
                        <label style="display: flex; align-items: center; gap: 0.5rem;">
                            <input type="checkbox" name="visible_to[]" value="supervisor">
                            Supervisors
                        </label>
                        <label style="display: flex; align-items: center; gap: 0.5rem;">
                            <input type="checkbox" name="visible_to[]" value="agent">
                            Agents
                        </label>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="memo_images" class="form-label">Images (Optional)</label>
                    <input type="file" name="memo_images[]" id="memo_images" class="form-control" accept="image/*" multiple>
                </div>
                
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary">Cancel</button>
                    <button type="submit" name="create_memo" class="btn btn-primary">Create Memo</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Memo Modal -->
<div id="editMemoModal" class="modal-overlay">
    <div class="modal">
        <div class="modal-header">
            <h3 class="modal-title">Edit Memo</h3>
            <button type="button" class="modal-close">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <div class="modal-body">
            <form action="" method="POST" enctype="multipart/form-data">
                <input type="hidden" id="edit_memo_id" name="memo_id">
                
                <div class="form-group">
                    <label for="edit_title" class="form-label">Memo Title</label>
                    <input type="text" id="edit_title" name="title" class="form-control" required>
                </div>
                
                <div class="form-group">
                    <label for="edit_content" class="form-label">Memo Content</label>
                    <textarea id="edit_content" name="content" class="form-control" required></textarea>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Visible to:</label>
                    <div style="display: flex; gap: 1rem; flex-wrap: wrap;">
                        <label style="display: flex; align-items: center; gap: 0.5rem;">
                            <input type="checkbox" id="edit_manager" name="visible_to[]" value="manager">
                            Managers
                        </label>
                        <label style="display: flex; align-items: center; gap: 0.5rem;">
                            <input type="checkbox" id="edit_supervisor" name="visible_to[]" value="supervisor">
                            Supervisors
                        </label>
                        <label style="display: flex; align-items: center; gap: 0.5rem;">
                            <input type="checkbox" id="edit_agent" name="visible_to[]" value="agent">
                            Agents
                        </label>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="edit_memo_images" class="form-label">Add More Images (Optional)</label>
                    <input type="file" name="memo_images[]" id="edit_memo_images" class="form-control" accept="image/*" multiple>
                </div>
                
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary">Cancel</button>
                    <button type="submit" name="update_memo" class="btn btn-primary">Update Memo</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// Add this to your existing JavaScript or create a new file
document.addEventListener('DOMContentLoaded', function() {
    // Create memo button
    const btnCreateMemo = document.getElementById('btnCreateMemo');
    if (btnCreateMemo) {
        btnCreateMemo.addEventListener('click', function() {
            document.getElementById('createMemoModal').classList.add('active');
        });
    }
});
</script>
