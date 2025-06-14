document.addEventListener("DOMContentLoaded", () => {
  // Tab functionality
  const tabs = document.querySelectorAll(".memo-tab")
  const tabContents = document.querySelectorAll(".tab-content")

  // Initialize tabs
  function initTabs() {
    // Get current tab from URL or default to 'all'
    const urlParams = new URLSearchParams(window.location.search)
    const activeTab = urlParams.get("tab") || "all"

    // Set active tab
    tabs.forEach((tab) => {
      const tabName = tab.getAttribute("data-tab")
      if (tabName === activeTab) {
        tab.classList.add("active")
      } else {
        tab.classList.remove("active")
      }
    })

    // Filter memos based on active tab
    filterMemos(activeTab)
  }

  // Tab click handlers
  tabs.forEach((tab) => {
    tab.addEventListener("click", function (e) {
      e.preventDefault()

      const tabName = this.getAttribute("data-tab")

      // Update URL
      const url = new URL(window.location)
      url.searchParams.set("tab", tabName)
      window.history.pushState({}, "", url)

      // Update active tab
      tabs.forEach((t) => t.classList.remove("active"))
      this.classList.add("active")

      // Filter memos
      filterMemos(tabName)
    })
  })

  // Filter memos based on tab
  function filterMemos(tabName) {
    const memoCards = document.querySelectorAll(".memo-card")

    memoCards.forEach((card) => {
      const visibleRoles = card.getAttribute("data-visible-roles") || ""
      let shouldShow = false

      switch (tabName) {
        case "all":
          shouldShow = true
          break
        case "recent":
          // Show memos from last 7 days
          const createdDate = new Date(card.getAttribute("data-created-date"))
          const weekAgo = new Date()
          weekAgo.setDate(weekAgo.getDate() - 7)
          shouldShow = createdDate >= weekAgo
          break
        case "manager":
          shouldShow = visibleRoles.includes("manager")
          break
        case "supervisor":
          shouldShow = visibleRoles.includes("supervisor")
          break
        case "agent":
          shouldShow = visibleRoles.includes("agent")
          break
        default:
          shouldShow = true
      }

      if (shouldShow) {
        card.style.display = "flex"
      } else {
        card.style.display = "none"
      }
    })

    // Check if any memos are visible
    const visibleMemos = document.querySelectorAll('.memo-card[style*="flex"]')
    const emptyState = document.querySelector(".empty-state")

    if (visibleMemos.length === 0) {
      if (!emptyState) {
        showEmptyState(tabName)
      }
    } else {
      if (emptyState) {
        emptyState.remove()
      }
    }
  }

  // Show empty state
  function showEmptyState(tabName) {
    const memoGrid = document.querySelector(".memo-grid")
    if (!memoGrid) return

    const emptyState = document.createElement("div")
    emptyState.className = "empty-state"

    let message = "No memos found"
    switch (tabName) {
      case "recent":
        message = "No recent memos found"
        break
      case "manager":
        message = "No memos for managers found"
        break
      case "supervisor":
        message = "No memos for supervisors found"
        break
      case "agent":
        message = "No memos for agents found"
        break
    }

    emptyState.innerHTML = `
            <div class="empty-state-icon">
                <i class="fas fa-file-alt"></i>
            </div>
            <h3 class="empty-state-title">${message}</h3>
            <p class="empty-state-text">Try switching to a different tab or create a new memo.</p>
        `

    memoGrid.appendChild(emptyState)
  }

  // Modal functionality
  const createMemoModal = document.getElementById("createMemoModal")
  const editMemoModal = document.getElementById("editMemoModal")
  const btnCreateMemo = document.getElementById("btnCreateMemo")

  // Create memo modal
  if (btnCreateMemo && createMemoModal) {
    btnCreateMemo.addEventListener("click", () => {
      createMemoModal.classList.add("active")
      document.body.style.overflow = "hidden"
    })

    // Close modal handlers
    const closeButtons = createMemoModal.querySelectorAll(".modal-close, .btn-secondary")
    closeButtons.forEach((btn) => {
      btn.addEventListener("click", () => {
        createMemoModal.classList.remove("active")
        document.body.style.overflow = ""
      })
    })

    // Close on background click
    createMemoModal.addEventListener("click", (e) => {
      if (e.target === createMemoModal) {
        createMemoModal.classList.remove("active")
        document.body.style.overflow = ""
      }
    })
  }

  // Edit memo modal
  if (editMemoModal) {
    const editButtons = document.querySelectorAll(".btn-icon.edit")

    editButtons.forEach((button) => {
      button.addEventListener("click", function () {
        const memoCard = this.closest(".memo-card")
        const memoId = memoCard.getAttribute("data-memo-id")
        const title = memoCard.querySelector(".memo-title").textContent
        const content = memoCard.querySelector(".memo-text").textContent
        const visibleRoles = memoCard.getAttribute("data-visible-roles").split(",")

        // Populate form
        document.getElementById("edit_memo_id").value = memoId
        document.getElementById("edit_title").value = title
        document.getElementById("edit_content").value = content

        // Set checkboxes
        document.getElementById("edit_manager").checked = visibleRoles.includes("manager")
        document.getElementById("edit_supervisor").checked = visibleRoles.includes("supervisor")
        document.getElementById("edit_agent").checked = visibleRoles.includes("agent")

        // Show modal
        editMemoModal.classList.add("active")
        document.body.style.overflow = "hidden"
      })
    })

    // Close modal handlers
    const closeButtons = editMemoModal.querySelectorAll(".modal-close, .btn-secondary")
    closeButtons.forEach((btn) => {
      btn.addEventListener("click", () => {
        editMemoModal.classList.remove("active")
        document.body.style.overflow = ""
      })
    })

    // Close on background click
    editMemoModal.addEventListener("click", (e) => {
      if (e.target === editMemoModal) {
        editMemoModal.classList.remove("active")
        document.body.style.overflow = ""
      }
    })
  }

  // Initialize tabs on page load
  initTabs()

  // Handle browser back/forward
  window.addEventListener("popstate", () => {
    initTabs()
  })
})
