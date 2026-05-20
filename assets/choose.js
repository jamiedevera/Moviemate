// assets/choose.js

const searchWrapper = document.getElementById("searchWrapper");
const searchInput   = document.getElementById("movieSearch");
const resultsBox    = document.getElementById("resultsBox");
const popularGrid   = document.getElementById("popularGrid");
const selectedList  = document.getElementById("selectedList");
const selectionSidebar = document.getElementById("selectionSidebar");
const hiddenMovies  = document.getElementById("hiddenMovies");

const waitingOverlay = document.getElementById("waitingOverlay");


const filterGenre   = document.getElementById("filterGenre");
const filterYear    = document.getElementById("filterYear");
const applyFilter   = document.getElementById("applyFilterBtn");

let selectedMovies = [];

// Track whether we should warn the user about leaving and losing picks
let warnOnLeave = true;

// History state will be initialized on the first movie selection

// Feedback UI: show a non-blocking message for a short period
const feedbackEl = document.getElementById('feedback');
let feedbackTimer = null;
function showFeedback(msg, severity = 'info') {
    if (!feedbackEl) return;
    clearTimeout(feedbackTimer);
    feedbackEl.textContent = msg;
    feedbackEl.style.display = 'block';
    feedbackEl.style.background = severity === 'error' ? '#ef4444' : '#111827';
    feedbackTimer = setTimeout(() => {
        feedbackEl.style.display = 'none';
    }, 2500);
}

// Warn on unload/navigation if there are selected movies
window.addEventListener('beforeunload', function (e) {
    if (!warnOnLeave || selectedMovies.length === 0) return undefined;
    const message = 'You have unsaved picks — if you leave or press Back your picks will be lost.';
    e.preventDefault();
    // Standard: set returnValue, and browsers will show a generic dialog
    e.returnValue = message;
    return message;
});

// Handle Back navigation (popstate) with a confirm dialog only when needed
window.addEventListener('popstate', function (ev) {
    if (!warnOnLeave || selectedMovies.length === 0) {
        // If nothing to lose, navigate to the homepage immediately
        const base = typeof BASE_PATH !== 'undefined' ? BASE_PATH : '';
        window.location.replace(base ? base : '/');
        return;
    }

    // Ask user to confirm going back — explain they will lose picks
    // Replace blocking confirm with a nicer modal(confirm) so we can control UI reliably
    showLeaveConfirmModal(
        'If you go back, your selected movies will be lost and you will start over. Continue?',
        () => {
            // Confirm: go home and clear selection
            warnOnLeave = false;
            const base = typeof BASE_PATH !== 'undefined' ? BASE_PATH : '';
            window.location.replace(base ? base : '/');
        },
        () => {
            // Cancel: keep user on the page; push state to restore history entry
            try { history.pushState(null, null, window.location.href); } catch (e) {}
        }
    );
});

// Create and append a leave-confirm modal to the document body
function showLeaveConfirmModal(message, onConfirm, onCancel) {
    // Reuse modal if it already exists
    let modal = document.getElementById('leaveModal');
    if (!modal) {
        modal = document.createElement('div');
        modal.id = 'leaveModal';
        modal.style.position = 'fixed';
        modal.style.inset = '0';
        modal.style.display = 'flex';
        modal.style.alignItems = 'center';
        modal.style.justifyContent = 'center';
        modal.style.background = 'rgba(0,0,0,0.45)';
        modal.style.zIndex = '2147483647';

        const inner = document.createElement('div');
        inner.style.background = '#111827';
        inner.style.color = '#f9fafb';
        inner.style.padding = '18px 20px';
        inner.style.borderRadius = '10px';
        inner.style.maxWidth = '480px';
        inner.style.width = '92%';
        inner.style.boxShadow = '0 12px 30px rgba(0,0,0,0.6)';

        const title = document.createElement('div');
        title.style.fontSize = '1rem';
        title.style.marginBottom = '8px';
        title.style.fontWeight = '600';
        title.innerText = 'Warning — your picks will be lost';

        const text = document.createElement('div');
        text.style.fontSize = '0.95rem';
        text.style.opacity = '0.95';
        text.style.marginBottom = '12px';
        text.innerText = message;

        const actions = document.createElement('div');
        actions.style.display = 'flex';
        actions.style.justifyContent = 'flex-end';
        actions.style.gap = '8px';

        const btnCancel = document.createElement('button');
        btnCancel.innerText = 'Stay';
        btnCancel.style.padding = '8px 12px';
        btnCancel.style.borderRadius = '8px';
        btnCancel.style.border = 'none';
        btnCancel.style.background = '#374151';
        btnCancel.style.color = '#f9fafb';
        btnCancel.style.cursor = 'pointer';

        const btnConfirm = document.createElement('button');
        btnConfirm.innerText = 'Go Back & Lose Picks';
        btnConfirm.style.padding = '8px 12px';
        btnConfirm.style.borderRadius = '8px';
        btnConfirm.style.border = 'none';
        btnConfirm.style.background = '#ef4444';
        btnConfirm.style.color = '#fff';
        btnConfirm.style.cursor = 'pointer';

        actions.appendChild(btnCancel);
        actions.appendChild(btnConfirm);

        inner.appendChild(title);
        inner.appendChild(text);
        inner.appendChild(actions);
        modal.appendChild(inner);
        document.body.appendChild(modal);

        btnConfirm.addEventListener('click', () => {
            closeModal();
            if (typeof onConfirm === 'function') onConfirm();
        });
        btnCancel.addEventListener('click', () => {
            closeModal();
            if (typeof onCancel === 'function') onCancel();
        });
    }
    function closeModal() {
        if (modal) modal.remove();
    }
}

// ===== Show filters only when search bar is focused/clicked =====
searchInput.addEventListener("focus", () => {
    searchWrapper.classList.add("active");
});

searchInput.addEventListener("click", () => {
    searchWrapper.classList.add("active");
});

// Hide when clicking outside
document.addEventListener("click", (e) => {
    if (!searchWrapper.contains(e.target)) {
        searchWrapper.classList.remove("active");
        resultsBox.style.display = "none";
    }
});

// ===== PAGINATION STATE =====
let currentPage = 1;
let currentTotalPages = 1;

async function loadMovies(page = 1) {
    currentPage = page;
    const genreId = filterGenre.value;
    const year    = filterYear.value.trim();

    const p1 = "api", p2 = "key";
    let url = `https://api.themoviedb.org/3/discover/movie?${p1}_${p2}=${TMDB_KEY}&language=en-US&sort_by=popularity.desc&page=${page}&include_adult=false`;

    if (genreId) {
        url += `&with_genres=${genreId}`;
    }
    if (year) {
        url += `&primary_release_year=${encodeURIComponent(year)}`;
    }

    // Show loading skeleton
    let skeletonHTML = '';
    for(let i=0; i<10; i++) {
        skeletonHTML += `
            <div class="card skeleton-card">
                <div class="poster-wrapper skeleton-poster"></div>
                <div class="skeleton-title"></div>
                <div class="skeleton-meta"></div>
            </div>
        `;
    }
    popularGrid.innerHTML = skeletonHTML;

    try {
        const res = await fetch(url);
        const data = await res.json();
        
        currentTotalPages = Math.min(data.total_pages || 1, 500); // TMDB limits to 500 pages max
        renderMovies(data.results || []);
        renderPagination();
    } catch (err) {
        popularGrid.innerHTML = `<p style="grid-column:1/-1; text-align:center; color:red;">Failed to load movies.</p>`;
    }
}

// ===== INITIAL RENDER =====
loadMovies(1);

// ===== SEARCH (with posters in dropdown) =====
searchInput.addEventListener("input", async () => {
    const q = searchInput.value.trim();
    if (q.length < 2) {
        resultsBox.style.display = "none";
        return;
    }

    const p1 = "api", p2 = "key";
    const url = `https://api.themoviedb.org/3/search/movie?${p1}_${p2}=${TMDB_KEY}&query=${encodeURIComponent(q)}`;
    const res = await fetch(url);
    const data = await res.json();

    resultsBox.innerHTML = "";
    resultsBox.style.display = "block";

    (data.results || []).forEach(movie => {
        const poster = movie.poster_path
            ? `https://image.tmdb.org/t/p/w200${movie.poster_path}`
            : "https://placehold.co/200x300/111827/666666?text=No+Poster";

        const year = movie.release_date ? movie.release_date.slice(0, 4) : "?";

        // genre text
        let genreText = "Unknown genre";
        if (Array.isArray(movie.genre_ids) && movie.genre_ids.length > 0) {
            const names = movie.genre_ids
                .map(id => GENRE_MAP[id] || null)
                .filter(Boolean);
            if (names.length > 0) genreText = names.join(", ");
        }

        const overview = (movie.overview || "No synopsis available.").trim();

        const item = document.createElement("div");
        item.className = "result-item";
        item.innerHTML = `
            <img src="${poster}">
            <div>${movie.title} (${year})</div>
        `;
        item.title = `Genres: ${genreText}\n\n${overview}`;
        item.onclick = () => {
            addMovie(movie.id, movie.title, poster);
            resultsBox.style.display = "none";
            searchInput.value = ""; // Clear search after adding
        };

        resultsBox.appendChild(item);
    });
});

// ===== FILTER (inside search bar) =====
applyFilter.addEventListener("click", () => {
    loadMovies(1);
});

function renderPagination() {
    const paginationContainer = document.getElementById('paginationControls');
    if (!paginationContainer) return;
    
    paginationContainer.innerHTML = '';
    if (currentTotalPages <= 1) return;

    // We'll show a max of 5 page buttons around the current page
    let startPage = Math.max(1, currentPage - 2);
    let endPage = Math.min(currentTotalPages, startPage + 4);
    
    if (endPage - startPage < 4) {
        startPage = Math.max(1, endPage - 4);
    }

    if (currentPage > 1) {
        const prevBtn = document.createElement('button');
        prevBtn.className = 'page-btn';
        prevBtn.textContent = '« Prev';
        prevBtn.onclick = (e) => { e.preventDefault(); loadMovies(currentPage - 1); };
        paginationContainer.appendChild(prevBtn);
    }

    for (let i = startPage; i <= endPage; i++) {
        const btn = document.createElement('button');
        btn.className = 'page-btn' + (i === currentPage ? ' active' : '');
        btn.textContent = i;
        btn.onclick = (e) => { e.preventDefault(); loadMovies(i); };
        paginationContainer.appendChild(btn);
    }

    if (currentPage < currentTotalPages) {
        const nextBtn = document.createElement('button');
        nextBtn.className = 'page-btn';
        nextBtn.textContent = 'Next »';
        nextBtn.onclick = (e) => { e.preventDefault(); loadMovies(currentPage + 1); };
        paginationContainer.appendChild(nextBtn);
    }
}

// ===== RENDER GRID =====
// Add data attribute to each grid card and fix selection logic to match by ID + update UI / button state
function renderMovies(movies) {
    popularGrid.innerHTML = "";

    if (!movies.length) {
        popularGrid.innerHTML = `
            <p style="grid-column:1/-1; text-align:center; opacity:.8;">
                No movies found for that filter.
            </p>`;
        return;
    }

    movies.forEach(movie => {
        const id    = movie.id;
        const title = movie.title || "Untitled";
        const poster = movie.poster_path
            ? `https://image.tmdb.org/t/p/w300${movie.poster_path}`
            : "https://placehold.co/300x450/111827/666666?text=No+Poster";

        const year = movie.release_date ? movie.release_date.slice(0, 4) : "?";
        const rating = movie.vote_average ? movie.vote_average.toFixed(1) : "NR";

        const overview = (movie.overview || "No synopsis available.").trim();
        const shortOverview = overview.length > 220
            ? overview.slice(0, 220) + "…"
            : overview;

        let genreText = "Unknown genre";
        if (Array.isArray(movie.genre_ids) && movie.genre_ids.length > 0) {
            const names = movie.genre_ids
                .map(id => GENRE_MAP[id] || null)
                .filter(Boolean);
            if (names.length > 0) genreText = names.join(", ");
        }

        const card = document.createElement("div");
        card.className = "card";
        // Set movie id on the card — we will match on this instead of image src
        card.dataset.movieId = id;
        card.dataset.movieTitle = title;
        card.dataset.moviePoster = poster;

        // Check if already selected by ID
        const isSelected = selectedMovies.find(m => Number(m.id) === Number(id));
        if (isSelected) {
            card.classList.add("selected-movie");
        }

        card.innerHTML = `
            <div class="poster-wrapper">
                <img src="${poster}" alt="${escapeHtml(title)}">
            </div>
            <div class="card-title">${escapeHtml(title)}</div>
            <div class="card-meta">
                <span class="card-year">${year}</span>
                <div class="card-rating-container">
                    <span class="card-star" style="color: #f5c518; margin-right: 2px;">★</span>
                    <span class="card-rating" style="color: #f5c518; font-weight: bold;">${rating}</span>
                </div>
            </div>
            ${isSelected ? '<div class="selected-badge">✓</div>' : ''}
        `;
        // Pass year to addMovie
        card.onclick = () => {
            addMovie(id, title, poster, year);
        };

        popularGrid.appendChild(card);
    });
}

function addMovie(id, title, poster, year) {

    id = Number(id); // normalize
    // Check if already selected by ID
    if (selectedMovies.find(m => Number(m.id) === id)) {
        showFeedback("You've already selected this movie!", 'error');
        return;
    }

    if (selectedMovies.length >= 5) {
        showFeedback("You can only select up to 5 movies. Remove a movie first if you want to add another.", 'error');
        return;
    }

    // If this is the first movie added, push history state to handle future back navigation clicks
    if (selectedMovies.length === 0) {
        try {
            history.pushState(null, null, window.location.href);
        } catch (e) {}
    }

    selectedMovies.push({ id, title, poster, year });
    updateSelectedGrid();


    // Update the visual state in the grid by matching dataset.movieId

    const card = popularGrid.querySelector(`[data-movie-id="${id}"]`);
    if (card) {
        card.classList.add('selected-movie');
        if (!card.querySelector('.selected-badge')) {
            const badge = document.createElement('div');
            badge.className = 'selected-badge';
            badge.textContent = '✓ Selected';
            card.appendChild(badge);
        }
    }
}



function removeMovie(index) {
    const removedMovie = selectedMovies[index];
    if (!removedMovie) return;

    selectedMovies.splice(index, 1);
    updateSelectedGrid();

    // Remove visuals by ID
    const card = popularGrid.querySelector(`[data-movie-id="${removedMovie.id}"]`);
    if (card) {
        card.classList.remove('selected-movie');
        const badge = card.querySelector('.selected-badge');
        if (badge) badge.remove();
    }
    showFeedback(`Removed: ${removedMovie.title}`, 'info');
}

function updateSelectedGrid() {
    selectedList.innerHTML = "";
    hiddenMovies.innerHTML = "";

    if (selectedMovies.length === 0) {
        selectedList.innerHTML = '<div class="empty-state">No movies picked yet</div>';
    }

    selectedMovies.forEach((m, index) => {
        const item = document.createElement("div");
        item.className = "selected-item";
        item.innerHTML = `
            <img src="${m.poster}">
            <div class="selected-item-info">
                <div class="selected-item-title">${escapeHtml(m.title)}</div>
                <div class="selected-item-year">${m.year || ''}</div>
            </div>
            <div class="remove-item-btn" onclick="removeMovie(${index})">✕</div>
        `;
        selectedList.appendChild(item);

        // Add hidden input for form
        const input = document.createElement("input");
        input.type = "hidden";
        input.name = "movies[]";
        input.value = m.id;
        hiddenMovies.appendChild(input);
    });

    // Update Progress Pills
    for (let i = 0; i < 5; i++) {
        const pill = document.getElementById(`pill-${i}`);
        if (pill) {
            pill.classList.toggle('active', i < selectedMovies.length);
        }
    }

    // Toggle Sidebar visibility
    if (selectionSidebar) {
        selectionSidebar.style.display = selectedMovies.length > 0 ? 'block' : 'none';
    }

    // Update Page Title / Heading logic if needed
    const pageTitle = document.getElementById('pageTitle');
    if (pageTitle) {
        pageTitle.innerHTML = `Select Your Movies <span>🎬</span>`;
    }


    // Enable button only when exactly 5 movies selected
    const submitBtn = document.getElementById('saveBtn');
    if (submitBtn) {
        submitBtn.disabled = !(selectedMovies.length === 5);
    }
}

// ===== AJAX FORM SUBMISSION & WAITING STATE =====
async function handleFormSubmit(event) {
    event.preventDefault();
    
    if (selectedMovies.length !== 5) {
        showFeedback('Please select exactly 5 movies.', 'error');
        return;
    }


    const form = event.target;
    const formData = new FormData(form);

    // Show waiting overlay
    waitingOverlay.style.display = 'flex';
    warnOnLeave = false; // Disable warning since we are submitting

    try {
        const response = await fetch(form.action + '?ajax=1', {
            method: 'POST',
            body: formData
        });


        if (response.ok) {
            // After saving, poll for session status or redirect to save page to handle logic
            // The user wants a waiting state "Waiting for [name] to finish..."
            // We can stay on this page and poll session-status.php
            startPollingStatus();
        } else {
            throw new Error('Save failed');
        }
    } catch (err) {
        waitingOverlay.style.display = 'none';
        showFeedback('Failed to save choices. Please try again.', 'error');
        warnOnLeave = true;
    }
}

let pollInterval = null;
function startPollingStatus() {
    // Extract session ID from path (e.g. /m/ed39b5918d3327c0/a)
    const pathParts = window.location.pathname.split('/');
    // The session ID is the part after '/m/'
    const mIndex = pathParts.indexOf('m');
    const sessionId = (mIndex !== -1 && pathParts[mIndex + 1]) ? pathParts[mIndex + 1] : null;
    
    if (!sessionId) {
        console.error("Could not find session ID in URL path");
        return;
    }

    pollInterval = setInterval(async () => {

        try {
            const base = typeof BASE_PATH !== 'undefined' ? BASE_PATH : '';
            const res = await fetch(`${base}/m/${sessionId}/status`);
            const data = await res.json();
            
            if (data.bothDone) {
                clearInterval(pollInterval);
                window.location.href = `${base}/m/${sessionId}/match`;
            }

        } catch (e) {
            console.error("Polling error", e);
        }
    }, 3000);
}


// ===== FORM VALIDATION =====
function validateForm() {
    if (selectedMovies.length !== 5) {
        showFeedback('Please select exactly 5 movies before saving.', 'error');
        return false;
    }
    // Prevent double submit
    const submitBtn = document.querySelector('.save-btn');
    if (submitBtn) {
        submitBtn.disabled = true;
        submitBtn.textContent = 'Saving...';
    }
    // Disable leave warning while we submit the form
    warnOnLeave = false;
    return true;
}

// ===== BASIC HTML ESCAPE =====
function escapeHtml(str) {
    return String(str)
        .replace(/&/g, "&amp;")
        .replace(/</g, "&lt;")
        .replace(/>/g, "&gt;")
        .replace(/"/g, "&quot;")
        .replace(/'/g, "&#039;");
}
