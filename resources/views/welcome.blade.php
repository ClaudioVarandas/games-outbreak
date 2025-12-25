<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ config('app.name') }}</title>

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-gray-900 text-white min-h-screen py-8 px-4">
<header class="text-center mb-12">
    <h1 class="text-5xl font-bold mb-8">Dezembro 2025 - Destaques</h1>

    <div class="flex flex-col lg:flex-row gap-6 justify-center items-center flex-wrap max-w-5xl mx-auto">
        <select id="platformFilter" class="px-6 py-3 bg-gray-800 border border-gray-700 rounded-xl focus:outline-none focus:border-blue-500 text-base">
            <option value="">All Platforms</option>
        </select>

        <div class="flex items-center gap-4">
            <button id="sortDateBtn" class="px-7 py-3 bg-blue-700 hover:bg-blue-600 rounded-xl text-base font-medium">Sort by Date</button>
            <button id="toggleDateDir" class="w-14 h-14 bg-gray-700 hover:bg-gray-600 rounded-xl flex items-center justify-center text-2xl font-bold">↑</button>
        </div>

        <div class="flex gap-4">
            <button id="gridViewBtn" class="px-8 py-3 bg-indigo-600 hover:bg-indigo-500 rounded-xl text-base font-semibold transition shadow-lg">Grid View</button>
            <button id="listViewBtn" class="px-8 py-3 bg-gray-700 hover:bg-gray-600 rounded-xl text-base font-semibold transition">List View</button>
        </div>
    </div>
</header>

<!-- GRID VIEW – 5 big cards per row -->
<div id="gridView" class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 gap-8 max-w-7xl mx-auto px-4"></div>

<!-- LIST VIEW -->
<div id="listView" class="hidden max-w-5xl mx-auto space-y-5 px-4"></div>

<script>
    const games = [ /* same games array – unchanged */
        { title: "Marvel Cosmic Invasion", cover: "https://images.igdb.com/igdb/image/upload/t_cover_big/coakkw.jpg", platforms: ["PS5","XSX","Switch 2","PC","Switch","PS4","Xbox One"], genres: ["Adventure","Fighting"], release: "2025-12-01" },
        { title: "Metroid Prime 4: Beyond", cover: "https://images.igdb.com/igdb/image/upload/t_cover_big/co9l1k.jpg", platforms: ["Switch 2","Switch"], genres: ["Adventure","Shooter"], release: "2025-12-04" },
        { title: "Octopath Traveler 0", cover: "https://images.igdb.com/igdb/image/upload/t_cover_big/coa7is.jpg", platforms: ["PS5","XSX","Switch 2","PC","Switch","PS4"], genres: ["RPG"], release: "2025-12-04" },
        { title: "Terminator 2D: No Fate", cover: "https://images.igdb.com/igdb/image/upload/t_cover_big/co9s6r.jpg", platforms: ["PS5","XSX","PC","Switch","PS4","Xbox One"], genres: ["Platform","Shooter"], release: "2025-12-12" },
        { title: "Red Dead Redemption", cover: "https://images.igdb.com/igdb/image/upload/t_cover_big/co2lcv.jpg", platforms: ["PS3","Xbox 360"], genres: ["Open World"], release: "2025-12-02" },
        { title: "Skate Story", cover: "https://images.igdb.com/igdb/image/upload/t_cover_big/co4xg0.jpg", platforms: ["Mac","PS5","Switch 2","PC"], genres: ["Sport","Indie"], release: "2025-12-08" },
        { title: "Code Violet", cover: "https://images.igdb.com/igdb/image/upload/t_cover_big/coa6dd.jpg", platforms: ["PS5"], genres: ["Shooter"], release: "2025-12-12" },
        { title: "Sleep Awake", cover: "https://images.igdb.com/igdb/image/upload/t_cover_big/co9xsd.jpg", platforms: ["PS5","XSX","PC"], genres: ["Adventure","Horror"], release: "2025-12-02" },
        { title: "Avatar: Frontiers of Pandora - From the Ashes", cover: "https://images.igdb.com/igdb/image/upload/t_cover_big/coaup9.webp", platforms: ["PS5","PC"], genres: ["Adventure","Shooter"], release: "2025-12-19" },
        { title: "Routine", cover: "https://images.igdb.com/igdb/image/upload/t_cover_big/coab7a.jpg", platforms: ["XSX","PC","Xbox One"], genres: ["Horror","Survival"], release: "2025-12-04" },
        { title: "Ferocious", cover: "https://images.igdb.com/igdb/image/upload/t_cover_big/co9y7q.jpg", platforms: ["PC"], genres: ["Shooter","Survival"], release: "2025-12-04" },
        { title: "NeverAwake Flashback", cover: "https://images.igdb.com/igdb/image/upload/t_cover_big/coaoxo.jpg", platforms: ["PC"], genres: ["Shooter","Horror"], release: "2025-12-10" },
        { title: "Assassin's Creed Shadows", cover: "https://images.igdb.com/igdb/image/upload/t_cover_big/co87cu.jpg", platforms: ["Switch 2"], genres: ["Action","Stealth"], release: "2025-12-02" },
        { title: "Cloudheim", cover: "https://images.igdb.com/igdb/image/upload/t_cover_big/coaqvh.jpg", platforms: ["PC"], genres: ["Adventure"], release: "2025-12-04" },
        { title: "Ashes of Creation", cover: "https://images.igdb.com/igdb/image/upload/t_cover_big/coaslp.jpg", platforms: ["PC"], genres: ["MMORPG"], release: "2025-12-11" },
        { title: "Echoes of Elysium", cover: "https://images.igdb.com/igdb/image/upload/t_cover_big/co9ykc.jpg", platforms: ["PC"], genres: ["Adventure"], release: "2025-12-04" },
        { title: "Northgard", cover: "https://images.igdb.com/igdb/image/upload/t_cover_big/guqifzispzew1rubnyzd.jpg", platforms: ["PC","PS4","Xbox One","Switch"], genres: ["Strategy"], release: "2025-12-04" },
        { title: "Night Swarm", cover: "https://images.igdb.com/igdb/image/upload/t_cover_big/co9t3c.jpg", platforms: ["PC"], genres: ["Action"], release: "2025-12-04" },
        { title: "Microsoft Flight Simulator 2024", cover: "https://images.igdb.com/igdb/image/upload/t_cover_big/co91qm.jpg", platforms: ["PS5"], genres: ["Simulator"], release: "2025-12-03" },
        { title: "Warhammer 40,000: Rogue Trader", cover: "https://images.igdb.com/igdb/image/upload/t_cover_big/co9eew.jpg", platforms: ["Switch 2"], genres: ["RPG"], release: "2025-12-11" },
        { title: "Sonic Racing: CrossWorlds", cover: "https://images.igdb.com/igdb/image/upload/t_cover_big/co9e16.jpg", platforms: ["Switch 2"], genres: ["Racing"], release: "2025-12-04" }
    ];

    const allPlatforms = [...new Set(games.flatMap(g => g.platforms))].sort();
    const platformSelect = document.getElementById("platformFilter");
    allPlatforms.forEach(p => {
        const opt = document.createElement("option");
        opt.value = p; opt.textContent = p;
        platformSelect.appendChild(opt);
    });

    let sortDir = "asc";
    let infoAlwaysVisible = true;
    let currentView = "grid";

    const gridView = document.getElementById("gridView");
    const listView = document.getElementById("listView");
    const gridBtn = document.getElementById("gridViewBtn");
    const listBtn = document.getElementById("listViewBtn");

    function createPlaceholder(title) {
        const canvas = document.createElement('canvas');
        canvas.width = 300; canvas.height = 440;
        const ctx = canvas.getContext('2d');
        ctx.fillStyle = '#1f2937'; ctx.fillRect(0,0,300,440);
        ctx.fillStyle = '#94a3b8'; ctx.font = '22px Arial'; ctx.textAlign = 'center'; ctx.textBaseline = 'middle';
        ctx.fillText(title, 150, 220, 280);
        return canvas.toDataURL();
    }

    function renderGridCard(game) {
        const placeholder = createPlaceholder(game.title);
        const date = new Date(game.release);
        const day = String(date.getDate()).padStart(2, '0');
        const month = String(date.getMonth() + 1).padStart(2, '0');
        const year = date.getFullYear();
        const displayDate = `${day}/${month}/${year}`;

        return `
        <div class="group relative bg-gray-800 rounded-xl overflow-hidden shadow-xl hover:shadow-2xl transition-all duration-400">
          <img src="${game.cover}" alt="${game.title}"
               class="w-full h-full object-cover group-hover:scale-110 transition-transform duration-500"
               onerror="this.src='${placeholder}'">

          <!-- BOTTOM OVERLAY WITH TITLE FIRST -->
          <div class="absolute inset-x-0 bottom-0 bg-gradient-to-t from-black/90 via-black/70 to-transparent px-4 pt-20
            opacity-100 translate-y-0
            transition-all duration-400 ease-out">

            <h3 class="text-md font-bold text-white mb-2 leading-tight">${game.title}</h3>
            <p class="text-md font-semibold text-cyan-300 mb-3">${displayDate}</p>


          </div>

        </div>`;
    }

    function renderListItem(game) {
        const date = new Date(game.release);
        const day = String(date.getDate()).padStart(2, '0');
        const month = String(date.getMonth() + 1).padStart(2, '0');
        const year = date.getFullYear();
        const displayDate = `${day}/${month}/${year}`;

        return `
        <div class="flex items-center gap-8 bg-gray-800/70 backdrop-blur-sm rounded-2xl p-6 hover:bg-gray-750 transition-all hover:scale-[1.01] shadow-lg">
          <img src="${game.cover}" alt="${game.title}" class="w-28 h-40 object-cover rounded-xl shadow-xl" onerror="this.src='${createPlaceholder(game.title)}'">

          <div class="flex-1">
            <h3 class="text-2xl font-bold mb-2">${game.title}</h3>
            <p class="text-cyan-300 text-lg font-medium mb-4">${displayDate}</p>

            <div class="flex flex-wrap gap-3 mb-4">
              ${game.platforms.map(p => `<span class="px-4 py-2 bg-green-900/80 text-sm font-medium rounded-full">${p}</span>`).join('')}
            </div>

            <div class="flex flex-wrap gap-2">
              ${game.genres.map(g => `<span class="px-3 py-1.5 bg-purple-900/70 text-xs rounded-lg">${g}</span>`).join('')}
            </div>
          </div>
        </div>`;
    }

    function render() {
        let list = [...games];
        const plat = platformSelect.value;
        if (plat) list = list.filter(g => g.platforms.includes(plat));

        list.sort((a, b) => sortDir === "asc"
            ? a.release.localeCompare(b.release)
            : b.release.localeCompare(a.release)
        );

        if (currentView === "grid") {
            gridView.innerHTML = list.map(renderGridCard).join("");
            listView.classList.add("hidden");
            gridView.classList.remove("hidden");
        } else {
            listView.innerHTML = list.map(renderListItem).join("");
            gridView.classList.add("hidden");
            listView.classList.remove("hidden");
        }
    }

    // View switching
    gridBtn.addEventListener("click", () => {
        currentView = "grid";
        gridBtn.classList.replace("bg-gray-700", "bg-indigo-600");
        listBtn.classList.replace("bg-indigo-600", "bg-gray-700");
        render();
    });

    listBtn.addEventListener("click", () => {
        currentView = "list";
        listBtn.classList.replace("bg-gray-700", "bg-indigo-600");
        gridBtn.classList.replace("bg-indigo-600", "bg-gray-700");
        render();
    });

    platformSelect.addEventListener("change", render);
    document.getElementById("toggleDateDir").addEventListener("click", () => {
        sortDir = sortDir === "asc" ? "desc" : "asc";
        document.getElementById("toggleDateDir").textContent = sortDir === "asc" ? "↑" : "↓";
        render();
    });

    render();
</script>
</body>
</html>
