// Simple, direct tab implementation
document.addEventListener("DOMContentLoaded", () => {
  // Get all tab buttons
  const tabButtons = document.querySelectorAll(".memo-tab")

  // Get all memo cards
  const memoCards = document.querySelectorAll(".memo-card")

  // Add click event to each tab button
  tabButtons.forEach((button) => {
    button.addEventListener("click", function () {
      // Remove active class from all buttons
      tabButtons.forEach((btn) => btn.classList.remove("active"))

      // Add active class to clicked button
      this.classList.add("active")

      // Get the tab value
      const tabValue = this.getAttribute("data-tab")

      // Filter memos based on tab
      filterMemos(tabValue)
    })
  })

  // Function to filter memos
  function filterMemos(tabValue) {
    // If tab is 'all', show all memos
    if (tabValue === "all") {
      memoCards.forEach((card) => {
        card.style.display = "flex"
      })
      return
    }

    // For other tabs, filter based on roles
    memoCards.forEach((card) => {
      const roles = card.getAttribute("data-roles")

      if (roles && roles.includes(tabValue)) {
        card.style.display = "flex"
      } else {
        card.style.display = "none"
      }
    })

    // Check if any memos are visible
    const visibleMemos = Array.from(memoCards).filter(
      (card) => card.style.display === "flex" || card.style.display === "",
    )

    // If no memos are visible, show empty state
    if (visibleMemos.length === 0) {
      const memoGrid = document.querySelector(".memo-grid")

      // Check if empty state already exists
      let emptyState = document.querySelector(".empty-state")
      if (!emptyState) {
        emptyState = document.createElement("div")
        emptyState.className = "empty-state"
        emptyState.innerHTML = `
                    <div class="empty-state-icon">
                        <i class="fas fa-file-alt"></i>
                    </div>
                    <h3 class="empty-state-title">No memos found</h3>
                    <p class="empty-state-text">No memos are available for this filter.</p>
                `
        memoGrid.appendChild(emptyState)
      }
    } else {
      // Remove empty state if it exists
      const emptyState = document.querySelector(".empty-state")
      if (emptyState) {
        emptyState.remove()
      }
    }
  }

  // Set the first tab as active by default
  if (tabButtons.length > 0) {
    tabButtons[0].classList.add("active")
    filterMemos(tabButtons[0].getAttribute("data-tab"))
  }
})
