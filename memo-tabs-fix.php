<?php
// This is a simplified version of the tabs and memo display
// Add this to your existing memo.php file or replace the relevant sections

// Make sure your memos are fetched from the database before this code
?>

<!-- Simplified Tab Structure -->
<div class="memo-tabs">
    <button class="memo-tab active" data-tab="all">All Memos</button>
    <?php if ($user['role'] == 'admin'): ?>
        <button class="memo-tab" data-tab="manager">Manager</button>
        <button class="memo-tab" data-tab="supervisor">Supervisor</button>
        <button class="memo-tab" data-tab="agent">Agent</button>
    <?php endif; ?>
</div>

<!-- Memo Grid with Larger Cards -->
<div class="memo-grid">
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
        <?php foreach ($memos as $memo): 
            // Get visible roles as a string for data attribute
            $visibleRoles = isset($memo['visible_roles']) ? $memo['visible_roles'] : '';
        ?>
            <div class="memo-card" data-memo-id="<?php echo $memo['memo_id']; ?>" data-roles="<?php echo $visibleRoles; ?>">
                <?php if (!empty($memo['images'])): ?>
                    <div class="memo-image">
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
                            <button class="btn-icon edit" onclick="editMemo(<?php echo $memo['memo_id']; ?>)" title="Edit">
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
    <?php endif; ?>
</div>

<!-- Add this script at the end of your file -->
<script>
// Simple inline script to make tabs work immediately
document.addEventListener('DOMContentLoaded', function() {
    // Get all tab buttons
    const tabButtons = document.querySelectorAll('.memo-tab');
    
    // Get all memo cards
    const memoCards = document.querySelectorAll('.memo-card');
    
    // Add click event to each tab button
    tabButtons.forEach(button => {
        button.addEventListener('click', function() {
            // Remove active class from all buttons
            tabButtons.forEach(btn => btn.classList.remove('active'));
            
            // Add active class to clicked button
            this.classList.add('active');
            
            // Get the tab value
            const tabValue = this.getAttribute('data-tab');
            
            // If tab is 'all', show all memos
            if (tabValue === 'all') {
                memoCards.forEach(card => {
                    card.style.display = 'flex';
                });
                return;
            }
            
            // For other tabs, filter based on roles
            memoCards.forEach(card => {
                const roles = card.getAttribute('data-roles');
                
                if (roles && roles.includes(tabValue)) {
                    card.style.display = 'flex';
                } else {
                    card.style.display = 'none';
                }
            });
        });
    });
});
</script>
