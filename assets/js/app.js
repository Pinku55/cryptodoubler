/* =====================================================================
   MTASK - Mini App front-end controller
   Vanilla ES6 + jQuery-free fetch. Handles Telegram auth, routing,
   page rendering, API calls, rewarded ads (Monetag), toasts & modals.
   ===================================================================== */
(function () {
    'use strict';

    // ----- Telegram WebApp handle -----
    const tg = window.Telegram ? window.Telegram.WebApp : null;
    if (tg) { tg.ready(); tg.expand(); }

    // Telegram user object (unsafe = client-side, used for avatar display).
    const tgUser = (tg && tg.initDataUnsafe && tg.initDataUnsafe.user) ? tg.initDataUnsafe.user : {};

    // ----- Global state -----
    const State = {
        initData: tg ? tg.initData : '',
        ref: new URLSearchParams(location.search).get('ref') || (tg && tg.initDataUnsafe && tg.initDataUnsafe.start_param) || '',
        tgPhoto: tgUser.photo_url || '',
        user: null,
        settings: {},
        recent: [],
        page: 'home'
    };

    const $ = (sel, root) => (root || document).querySelector(sel);
    const $$ = (sel, root) => Array.from((root || document).querySelectorAll(sel));
    const app = $('#app');

    // ----- Helpers -----
    const fmt = (n) => Number(n || 0).toLocaleString('en-US');
    const esc = (s) => String(s == null ? '' : s).replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
    const sym = () => State.settings.currency_symbol || 'MT';

    function timeAgo(dateStr) {
        if (!dateStr) return '';
        const d = new Date(dateStr.replace(' ', 'T'));
        const s = Math.floor((Date.now() - d.getTime()) / 1000);
        if (s < 60) return 'just now';
        if (s < 3600) return Math.floor(s / 60) + 'm ago';
        if (s < 86400) return Math.floor(s / 3600) + 'h ago';
        return d.toLocaleDateString();
    }

    // ----- API client -----
    async function api(endpoint, params) {
        const body = new FormData();
        body.append('initData', State.initData);
        if (State.ref) body.append('ref', State.ref);
        if (State.tgPhoto) body.append('tg_photo', State.tgPhoto);
        Object.entries(params || {}).forEach(([k, v]) => body.append(k, v));
        try {
            const res = await fetch(window.MTASK.apiBase + endpoint, {
                method: 'POST',
                headers: { 'X-Telegram-InitData': State.initData },
                body
            });
            const json = await res.json();
            if (!json.ok) { toast(json.message || 'Something went wrong', 'error'); }
            return json;
        } catch (e) {
            toast('Network error. Please try again.', 'error');
            return { ok: false, message: 'network', data: null };
        }
    }

    // ----- Toast -----
    let toastSeq = 0;
    function toast(msg, type) {
        const host = $('#toastHost');
        const el = document.createElement('div');
        el.className = 'toast-msg ' + (type || '');
        el.textContent = msg;
        host.appendChild(el);
        requestAnimationFrame(() => el.classList.add('show'));
        const id = ++toastSeq;
        setTimeout(() => { el.classList.remove('show'); setTimeout(() => el.remove(), 300); }, 3200);
        if (tg && tg.HapticFeedback && type === 'success') tg.HapticFeedback.notificationOccurred('success');
        return id;
    }

    // ----- Modal -----
    function openModal(html) {
        $('#modalCard').innerHTML = html;
        $('#modalHost').hidden = false;
    }
    function closeModal() { $('#modalHost').hidden = true; }
    $('#modalHost').addEventListener('click', (e) => { if (e.target.id === 'modalHost') closeModal(); });

    // ----- Side menu -----
    function toggleMenu(open) {
        $('#sideMenu').classList.toggle('open', open);
        $('#overlay').classList.toggle('show', open);
    }
    $('#menuBtn').addEventListener('click', () => toggleMenu(true));
    $('#overlay').addEventListener('click', () => toggleMenu(false));
    $('#notifBtn').addEventListener('click', () => {
        if (State.settings.announcement) toast(State.settings.announcement, 'warn');
        else toast('No new notifications', '');
    });

    // ----- Navigation -----
    document.addEventListener('click', (e) => {
        const goEl = e.target.closest('[data-go]');
        if (goEl) { e.preventDefault(); navigate(goEl.getAttribute('data-go')); toggleMenu(false); }
    });

    function setActiveNav(page) {
        const map = { home: 'home', earn: 'earn', wallet: 'wallet', referrals: 'referrals', profile: 'profile' };
        const navKey = map[page] || (['tasks', 'bonus', 'ads'].includes(page) ? 'earn' : (page === 'withdraw' ? 'wallet' : page));
        $$('.bottom-nav .nav-item').forEach(i => i.classList.toggle('active', i.getAttribute('data-go') === navKey));
    }

    function navigate(page) {
        State.page = page;
        setActiveNav(page);
        app.scrollTo ? window.scrollTo(0, 0) : null;
        const routes = {
            home: renderHome, earn: renderEarn, ads: renderAds, tasks: renderTasks,
            bonus: renderBonus, wallet: renderWallet, withdraw: renderWithdraw,
            referrals: renderReferrals, profile: renderProfile
        };
        (routes[page] || renderHome)();
    }

    function skeleton() {
        app.innerHTML = '<div class="skeleton-wrap"><div class="skeleton skeleton-card"></div><div class="skeleton skeleton-row"></div><div class="skeleton skeleton-row"></div></div>';
    }

    // ===================================================================
    // PAGE: HOME
    // ===================================================================
    function renderHome() {
        const u = State.user;
        app.className = 'app-main fade-in';
        app.innerHTML = `
            <div class="balance-card pop-in">
                <div class="balance-label">Current Balance</div>
                <div class="balance-amount">${fmt(u.balance)} <span style="font-size:18px">${esc(sym())}</span></div>
                <div class="balance-usd">≈ $${(u.balance_usd || 0).toFixed(4)} USD</div>
                <div class="coin"><i class="fa-solid fa-coins"></i></div>
            </div>

            <div class="stat-grid stagger">
                <div class="stat-card bg-purple"><div class="v">${fmt(u.total_earned)}</div><div class="l"><i class="fa-solid fa-sack-dollar"></i> Total Earned</div></div>
                <div class="stat-card bg-blue"><div class="v">${fmt(u.total_referrals)}</div><div class="l"><i class="fa-solid fa-user-group"></i> Referrals</div></div>
                <div class="stat-card bg-green"><div class="v">${fmt(u.total_withdrawn)}</div><div class="l"><i class="fa-solid fa-money-bill-transfer"></i> Withdrawn</div></div>
            </div>

            <div class="quick-card pop-in" data-go="earn">
                <div><h4>Start Earning Now!</h4><p>Watch ads &amp; complete tasks</p></div>
                <div class="gift bounce">🎁</div>
            </div>

            <div class="quick-btns stagger">
                <button class="quick-btn qb-ads" data-go="ads"><i class="fa-solid fa-circle-play"></i><span>Watch Ads</span></button>
                <button class="quick-btn qb-tasks" data-go="tasks"><i class="fa-solid fa-list-check"></i><span>Tasks</span></button>
                <button class="quick-btn qb-bonus" data-go="bonus"><i class="fa-solid fa-gift"></i><span>Daily Bonus</span></button>
                <button class="quick-btn qb-ref" data-go="referrals"><i class="fa-solid fa-users"></i><span>Referrals</span></button>
            </div>

            <div class="section-title">Recent Activity <a data-go="wallet">See all</a></div>
            <div id="recentList"></div>
        `;
        renderActivityList($('#recentList'), State.recent);
    }

    function txMeta(type) {
        const m = {
            ad: { i: 'bi-play-btn-fill', c: 'bg-purple', t: 'Rewarded Ad' },
            task: { i: 'bi-check2-circle', c: 'bg-green', t: 'Task Reward' },
            daily_bonus: { i: 'bi-gift-fill', c: 'bg-orange', t: 'Daily Bonus' },
            referral: { i: 'bi-people-fill', c: 'bg-blue', t: 'Referral Bonus' },
            withdraw: { i: 'bi-cash-coin', c: 'bg-purple', t: 'Withdrawal' },
            admin_adjust: { i: 'bi-sliders', c: 'bg-blue', t: 'Adjustment' },
            refund: { i: 'bi-arrow-counterclockwise', c: 'bg-green', t: 'Refund' }
        };
        return m[type] || { i: 'bi-cash', c: 'bg-purple', t: type };
    }

    function renderActivityList(container, items) {
        if (!items || !items.length) {
            container.innerHTML = '<div class="empty"><i class="bi bi-inbox"></i>No activity yet</div>';
            return;
        }
        container.innerHTML = items.map(t => {
            const meta = txMeta(t.type);
            const pos = Number(t.amount) >= 0;
            return `<div class="activity-item">
                <div class="activity-icon ${meta.c}"><i class="bi ${meta.i}"></i></div>
                <div class="activity-body">
                    <div class="activity-title">${esc(t.note || meta.t)}</div>
                    <div class="activity-date">${timeAgo(t.created_at)}</div>
                </div>
                <div class="activity-amount ${pos ? 'amount-pos' : 'amount-neg'}">${pos ? '+' : ''}${fmt(t.amount)}</div>
            </div>`;
        }).join('');
    }

    // ===================================================================
    // PAGE: EARN (hub)
    // ===================================================================
    function renderEarn() {
        app.className = 'app-main fade-in';
        app.innerHTML = `
            <div class="page-title">Earn MT</div>
            <div class="page-header hdr-purple" data-go="ads" style="cursor:pointer">
                <h2><i class="bi bi-play-btn-fill"></i> Watch Ads</h2>
                <p>Earn ${fmt(State.settings.ad_reward)} ${esc(sym())} per ad</p>
            </div>
            <div class="page-header hdr-green mt-12" data-go="tasks" style="cursor:pointer">
                <h2><i class="bi bi-list-check"></i> Tasks</h2>
                <p>Complete simple tasks for big rewards</p>
            </div>
            <div class="page-header hdr-orange mt-12" data-go="bonus" style="cursor:pointer">
                <h2><i class="bi bi-gift-fill"></i> Daily Bonus</h2>
                <p>Claim every day &amp; build your streak</p>
            </div>
            <div class="page-header hdr-blue mt-12" data-go="referrals" style="cursor:pointer">
                <h2><i class="bi bi-people-fill"></i> Refer &amp; Earn</h2>
                <p>Get ${fmt(State.settings.referral_reward)} ${esc(sym())} per friend</p>
            </div>`;
    }

    // ===================================================================
    // PAGE: WATCH ADS
    // ===================================================================
    let adTimer = null;
    async function renderAds() {
        clearInterval(adTimer);
        skeleton();
        const res = await api('ads.php', { action: 'status' });
        if (!res.ok) { app.innerHTML = errBox(res.message); return; }
        const s = res.data;
        app.className = 'app-main fade-in';
        const pct = s.daily_limit > 0 ? Math.min(100, (s.today_count / s.daily_limit) * 100) : 0;
        app.innerHTML = `
            <div class="page-header hdr-purple ad-hero">
                <div class="ad-icon pulse"><i class="fa-solid fa-clapperboard"></i></div>
                <div class="ad-reward-big">+${fmt(s.reward)} ${esc(sym())}</div>
                <p>Reward per ad</p>
                <div class="progress-track"><div class="progress-fill" style="width:${pct}%"></div></div>
                <p style="margin-top:8px"><i class="fa-solid fa-chart-simple"></i> ${s.today_count}/${s.daily_limit > 0 ? s.daily_limit : '∞'} ads today</p>
            </div>

            <div class="card-soft mt-12 fade-in">
                <h5 style="font-weight:800"><i class="fa-solid fa-circle-info" style="color:var(--purple)"></i> How it works</h5>
                <ol class="text-muted2" style="padding-left:18px;font-size:14px;line-height:1.9">
                    <li>Tap "Watch Ad Now"</li><li>Complete the full ad</li>
                    <li>Receive your reward instantly</li><li>Wait ${s.cooldown}s, then repeat!</li>
                </ol>
            </div>

            <button class="btn-grad mt-12 pulse" id="watchAdBtn">
                <i class="fa-solid fa-circle-play"></i> <span id="adBtnText">Watch Ad Now</span>
            </button>
            <p class="center text-muted2 mt-12" id="adHint"><i class="fa-solid fa-shield-halved"></i> Reward is credited only after the ad completes.</p>`;

        const btn = $('#watchAdBtn');

        // Start the cooldown / "waiting time" countdown if needed.
        function startCooldown(seconds) {
            clearInterval(adTimer);
            btn.disabled = true;
            btn.classList.remove('pulse');
            let left = Math.max(0, parseInt(seconds, 10) || 0);
            const tick = () => {
                if (left <= 0) {
                    clearInterval(adTimer);
                    btn.disabled = false;
                    btn.classList.add('pulse');
                    $('#adBtnText').textContent = 'Watch Ad Now';
                    $('#adHint').innerHTML = '<i class="fa-solid fa-shield-halved"></i> Reward is credited only after the ad completes.';
                    return;
                }
                $('#adBtnText').textContent = 'Please wait ' + left + 's';
                $('#adHint').innerHTML = '<i class="fa-regular fa-clock"></i> Next ad available in <b>' + left + 's</b>';
                left--;
            };
            tick();
            adTimer = setInterval(tick, 1000);
        }

        if (s.daily_limit > 0 && s.remaining <= 0) {
            btn.disabled = true;
            btn.classList.remove('pulse');
            $('#adBtnText').textContent = 'Daily limit reached';
            $('#adHint').innerHTML = '<i class="fa-solid fa-hourglass-end"></i> Come back tomorrow for more ads.';
        } else if (s.cooldown_left > 0) {
            startCooldown(s.cooldown_left);
        }

        btn.addEventListener('click', () => watchAd(btn, s.cooldown));
    }

    function watchAd(btn, cooldown) {
        const zone = window.MTASK.monetagZone;
        const fnName = 'show_' + zone;
        clearInterval(adTimer);
        btn.disabled = true;
        btn.classList.remove('pulse');
        $('#adBtnText').textContent = 'Loading ad...';
        $('#adHint').innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Loading your ad…';

        const reward = async () => {
            const res = await api('ads.php', { action: 'claim' });
            if (res.ok) {
                toast(res.message, 'success');
                State.user.balance = res.data.balance;
                renderAds(); // re-render -> shows the waiting countdown
            } else {
                btn.disabled = false;
                btn.classList.add('pulse');
                $('#adBtnText').textContent = 'Watch Ad Now';
            }
        };

        const failed = () => {
            toast('Ad was not completed.', 'warn');
            btn.disabled = false;
            btn.classList.add('pulse');
            $('#adBtnText').textContent = 'Watch Ad Now';
            $('#adHint').innerHTML = '<i class="fa-solid fa-triangle-exclamation"></i> Please watch the full ad to earn.';
        };

        if (typeof window[fnName] === 'function') {
            // New Monetag rewarded interstitial: show_<zone>().then(reward)
            // Reward is granted ONLY after the promise resolves (ad completed).
            try {
                const p = window[fnName]();
                if (p && typeof p.then === 'function') {
                    p.then(reward).catch(failed);
                } else {
                    reward();
                }
            } catch (e) {
                failed();
            }
        } else {
            // Fallback (SDK not loaded, e.g. outside Telegram): enforce a short
            // waiting time then reward so the flow remains testable.
            let left = 5;
            $('#adBtnText').textContent = 'Ad playing ' + left + 's';
            const iv = setInterval(() => {
                left--;
                if (left <= 0) { clearInterval(iv); reward(); }
                else $('#adBtnText').textContent = 'Ad playing ' + left + 's';
            }, 1000);
        }
    }

    // ===================================================================
    // PAGE: TASKS
    // ===================================================================
    const TASK_CAT = {
        website: { i: 'bi-globe', c: 'bg-blue' }, shortlink: { i: 'bi-link-45deg', c: 'bg-purple' },
        telegram_channel: { i: 'bi-telegram', c: 'bg-blue' }, telegram_group: { i: 'bi-telegram', c: 'bg-blue' },
        telegram_bot: { i: 'bi-robot', c: 'bg-purple' }, instagram: { i: 'bi-instagram', c: 'bg-orange' },
        facebook: { i: 'bi-facebook', c: 'bg-blue' }, twitter: { i: 'bi-twitter-x', c: 'bg-purple' },
        youtube: { i: 'bi-youtube', c: 'bg-orange' }, survey: { i: 'bi-clipboard-check', c: 'bg-green' },
        other: { i: 'bi-star', c: 'bg-purple' }
    };
    async function renderTasks() {
        skeleton();
        const res = await api('tasks.php', { action: 'list' });
        if (!res.ok) { app.innerHTML = errBox(res.message); return; }
        app.className = 'app-main fade-in';
        const tasks = res.data.tasks || [];
        const cards = tasks.length ? tasks.map(t => {
            const cat = TASK_CAT[t.category] || TASK_CAT.other;
            const done = t.user_status === 'completed';
            return `<div class="task-card" data-task='${esc(JSON.stringify({ id: t.id, wait: t.wait_time, url: t.url, status: t.user_status }))}'>
                <div class="task-cat ${cat.c}"><i class="bi ${cat.i}"></i></div>
                <div class="task-info">
                    <h5>${esc(t.title)}</h5>
                    <span class="reward">+${fmt(t.reward)} ${esc(sym())}</span>
                </div>
                <button class="task-btn ${done ? 'done' : ''}" ${done ? 'disabled' : ''}>${done ? 'Done' : 'Start'}</button>
            </div>`;
        }).join('') : '<div class="empty"><i class="bi bi-list-check"></i>No tasks available right now</div>';

        app.innerHTML = `
            <div class="page-header hdr-green"><h2><i class="bi bi-list-check"></i> Tasks</h2><p>Complete tasks to earn ${esc(sym())}</p></div>
            <div class="mt-12">${cards}</div>`;

        $$('.task-card .task-btn').forEach(b => {
            b.addEventListener('click', () => {
                const data = JSON.parse(b.closest('.task-card').getAttribute('data-task'));
                startTask(data, b);
            });
        });
    }

    async function startTask(t, btn) {
        if (t.status === 'completed') return;
        btn.disabled = true;
        const res = await api('tasks.php', { action: 'start', task_id: t.id });
        if (!res.ok) { btn.disabled = false; return; }
        if (t.url) { if (tg && tg.openLink) tg.openLink(t.url); else window.open(t.url, '_blank'); }

        let left = res.data.wait_time || t.wait;
        btn.textContent = left + 's';
        const iv = setInterval(async () => {
            left--;
            if (left > 0) { btn.textContent = left + 's'; return; }
            clearInterval(iv);
            btn.textContent = 'Claim';
            btn.disabled = false;
            btn.onclick = async () => {
                btn.disabled = true;
                const c = await api('tasks.php', { action: 'claim', task_id: t.id });
                if (c.ok) {
                    toast(c.message, 'success');
                    State.user.balance = c.data.balance;
                    btn.textContent = 'Done'; btn.classList.add('done');
                } else { btn.disabled = false; btn.textContent = 'Claim'; }
            };
        }, 1000);
    }

    // ===================================================================
    // PAGE: DAILY BONUS
    // ===================================================================
    async function renderBonus() {
        skeleton();
        const res = await api('bonus.php', { action: 'status' });
        if (!res.ok) { app.innerHTML = errBox(res.message); return; }
        const s = res.data;
        app.className = 'app-main fade-in';
        const days = Object.keys(s.ladder).map(Number).sort((a, b) => a - b);
        const grid = days.map(d => {
            const claimed = d <= s.current_day && !(s.next_day === 1 && !s.claimed_today && d > 0 && s.current_day === 0);
            const isClaimed = d <= s.current_day && (s.claimed_today || d < s.next_day || (d <= s.current_day));
            const cur = (d === s.next_day && !s.claimed_today);
            return `<div class="bonus-day ${d <= s.current_day ? 'claimed' : ''} ${cur ? 'current' : ''}">
                <div class="d">Day ${d}</div>
                <div class="ico">${d <= s.current_day ? '✅' : '🎁'}</div>
                <div class="r">${fmt(s.ladder[d])}</div>
            </div>`;
        }).join('');

        app.innerHTML = `
            <div class="page-header hdr-orange">
                <h2>🔥 Daily Bonus</h2>
                <p>Current streak: <b>${s.current_day} day(s)</b></p>
            </div>
            <div class="bonus-grid">${grid}</div>
            <button class="btn-grad btn-orange mt-12" id="claimBonus" ${s.claimed_today ? 'disabled' : ''}>
                ${s.claimed_today ? 'Already claimed today' : 'Claim Day ' + s.next_day + ' (+' + fmt(s.next_reward) + ' ' + sym() + ')'}
            </button>
            <p class="center text-muted2 mt-12">Miss a day and your streak resets!</p>`;

        const cb = $('#claimBonus');
        if (cb && !s.claimed_today) cb.addEventListener('click', async () => {
            cb.disabled = true;
            const r = await api('bonus.php', { action: 'claim' });
            if (r.ok) { toast(r.message, 'success'); State.user.balance = r.data.balance; renderBonus(); }
            else cb.disabled = false;
        });
    }

    // ===================================================================
    // PAGE: WALLET
    // ===================================================================
    let walletFilter = '';
    async function renderWallet() {
        skeleton();
        const res = await api('wallet.php', { type: walletFilter });
        if (!res.ok) { app.innerHTML = errBox(res.message); return; }
        const d = res.data;
        app.className = 'app-main fade-in';
        const filters = [['', 'All'], ['ad', 'Ads'], ['task', 'Tasks'], ['referral', 'Referral'], ['withdraw', 'Withdraw']];
        app.innerHTML = `
            <div class="balance-card">
                <div class="balance-label">Available Balance</div>
                <div class="balance-amount">${fmt(d.balance)} <span style="font-size:18px">${esc(sym())}</span></div>
                <div class="balance-usd">≈ $${(d.balance_usd || 0).toFixed(4)} USD</div>
                <div class="coin"><i class="bi bi-wallet2"></i></div>
            </div>
            <div class="stat-grid">
                <div class="stat-card bg-orange"><div class="v">${fmt(d.pending)}</div><div class="l">Pending</div></div>
                <div class="stat-card bg-green"><div class="v">${fmt(d.lifetime_earnings)}</div><div class="l">Lifetime Earned</div></div>
                <div class="stat-card bg-blue"><div class="v">${fmt(d.lifetime_withdraw)}</div><div class="l">Withdrawn</div></div>
            </div>
            <button class="btn-grad mt-12" data-go="withdraw"><i class="bi bi-cash-coin"></i> Withdraw</button>
            <div class="section-title">Transactions</div>
            <div style="display:flex;gap:8px;overflow-x:auto;padding-bottom:8px">
                ${filters.map(f => `<button class="task-btn ${walletFilter === f[0] ? '' : 'btn-outline'}" data-filter="${f[0]}" style="${walletFilter === f[0] ? '' : 'background:rgba(124,58,237,.08);color:var(--purple)'}">${f[1]}</button>`).join('')}
            </div>
            <div id="txList"></div>`;
        renderActivityList($('#txList'), d.transactions.items);
        $$('[data-filter]').forEach(b => b.addEventListener('click', () => { walletFilter = b.getAttribute('data-filter'); renderWallet(); }));
    }

    // ===================================================================
    // PAGE: WITHDRAW
    // ===================================================================
    let selectedMethod = null;
    async function renderWithdraw() {
        skeleton();
        const res = await api('withdraw.php', { action: 'methods' });
        if (!res.ok) { app.innerHTML = errBox(res.message); return; }
        const d = res.data;
        app.className = 'app-main fade-in';
        const chips = d.methods.map(m => `
            <div class="method-chip" data-method='${esc(JSON.stringify(m))}'>
                <i class="bi ${esc(m.icon || 'bi-wallet2')}"></i><span>${esc(m.name)}</span>
            </div>`).join('');
        app.innerHTML = `
            <div class="page-header hdr-blue">
                <h2><i class="bi bi-cash-coin"></i> Withdraw</h2>
                <p>Balance: <b>${fmt(d.balance)} ${esc(sym())}</b> · Min: ${fmt(d.min_withdraw)} ${esc(sym())}</p>
            </div>
            <div class="section-title">Choose Method</div>
            <div class="method-grid">${chips}</div>
            <div id="wdForm"></div>
            <div class="section-title">History</div>
            <div id="wdHistory"><div class="empty"><i class="bi bi-clock-history"></i>Loading…</div></div>`;

        $$('.method-chip').forEach(c => c.addEventListener('click', () => {
            $$('.method-chip').forEach(x => x.classList.remove('active'));
            c.classList.add('active');
            selectedMethod = JSON.parse(c.getAttribute('data-method'));
            renderWithdrawForm(d);
        }));

        const hist = await api('withdraw.php', { action: 'history' });
        if (hist.ok) renderWithdrawHistory(hist.data.withdrawals);
    }

    function renderWithdrawForm(d) {
        const m = selectedMethod;
        const fields = (m.fields || []).map(f => `
            <div class="field">
                <label>${esc(f.label)}${f.required ? ' *' : ''}</label>
                <input type="${esc(f.type || 'text')}" id="wf_${esc(f.name)}" placeholder="${esc(f.label)}">
            </div>`).join('');
        const usd = (Number(d.mt_per_usd) > 0) ? (m.min_amount / d.mt_per_usd).toFixed(2) : '0';
        $('#wdForm').innerHTML = `
            <div class="card-soft fade-in">
                <div class="field">
                    <label>Amount (${esc(sym())}) — min ${fmt(m.min_amount)} (≈ $${usd})</label>
                    <input type="number" id="wdAmount" min="${m.min_amount}" value="${m.min_amount}">
                </div>
                ${fields}
                <button class="btn-grad btn-blue" id="wdSubmit">Request Withdrawal</button>
            </div>`;
        $('#wdSubmit').addEventListener('click', submitWithdraw);
    }

    async function submitWithdraw() {
        const m = selectedMethod;
        const btn = $('#wdSubmit');
        const params = { action: 'request', method_id: m.id, amount: $('#wdAmount').value };
        (m.fields || []).forEach(f => { params['field_' + f.name] = ($('#wf_' + f.name) || {}).value || ''; });
        btn.disabled = true;
        const res = await api('withdraw.php', params);
        if (res.ok) { toast(res.message, 'success'); renderWithdraw(); }
        else btn.disabled = false;
    }

    function renderWithdrawHistory(items) {
        const host = $('#wdHistory');
        if (!items || !items.length) { host.innerHTML = '<div class="empty"><i class="bi bi-clock-history"></i>No withdrawals yet</div>'; return; }
        host.innerHTML = items.map(w => `
            <div class="activity-item">
                <div class="activity-icon bg-blue"><i class="bi bi-cash-coin"></i></div>
                <div class="activity-body">
                    <div class="activity-title">${esc(w.method_name)} · ${fmt(w.amount_mt)} ${esc(sym())}</div>
                    <div class="activity-date">${timeAgo(w.created_at)}</div>
                </div>
                <span class="badge-status st-${esc(w.status)}">${esc(w.status)}</span>
            </div>`).join('');
    }

    // ===================================================================
    // PAGE: REFERRALS
    // ===================================================================
    async function renderReferrals() {
        skeleton();
        const res = await api('referrals.php', {});
        if (!res.ok) { app.innerHTML = errBox(res.message); return; }
        const d = res.data;
        app.className = 'app-main fade-in';
        const list = (d.referred || []).length
            ? d.referred.map(r => `<div class="activity-item"><div class="activity-icon bg-blue"><i class="bi bi-person-fill"></i></div><div class="activity-body"><div class="activity-title">${esc(r.first_name || 'User')}</div><div class="activity-date">${timeAgo(r.created_at)}</div></div></div>`).join('')
            : '<div class="empty"><i class="bi bi-people"></i>No referrals yet. Start inviting!</div>';
        app.innerHTML = `
            <div class="page-header hdr-blue">
                <h2><i class="bi bi-people-fill"></i> Refer &amp; Earn</h2>
                <p>Earn ${fmt(d.reward_per_ref)} ${esc(sym())} for every friend!</p>
                <div class="ref-code-box">
                    <span class="code">${esc(d.code)}</span>
                    <button class="copy-btn" id="copyCode">Copy</button>
                </div>
            </div>
            <div class="stat-grid">
                <div class="stat-card bg-purple"><div class="v">${fmt(d.total_referrals)}</div><div class="l">Referrals</div></div>
                <div class="stat-card bg-green"><div class="v">${fmt(d.total_earned)}</div><div class="l">Earned</div></div>
                <div class="stat-card bg-orange"><div class="v">${fmt(d.reward_per_ref)}</div><div class="l">Per Invite</div></div>
            </div>
            <button class="btn-grad btn-blue mt-12" id="shareBtn"><i class="bi bi-telegram"></i> Share Invite Link</button>
            <button class="btn-grad btn-outline mt-12" id="copyLink"><i class="bi bi-link-45deg"></i> Copy Link</button>
            <div class="section-title">Your Referrals</div>
            ${list}`;

        const copy = (txt) => { navigator.clipboard ? navigator.clipboard.writeText(txt).then(() => toast('Copied!', 'success')) : toast(txt, ''); };
        $('#copyCode').addEventListener('click', () => copy(d.code));
        $('#copyLink').addEventListener('click', () => copy(d.link));
        $('#shareBtn').addEventListener('click', () => {
            const text = 'Join me on ' + (State.settings.site_name || 'MTASK') + ' and start earning! 🚀';
            const url = 'https://t.me/share/url?url=' + encodeURIComponent(d.link) + '&text=' + encodeURIComponent(text);
            if (tg && tg.openTelegramLink) tg.openTelegramLink(url); else window.open(url, '_blank');
        });
    }

    // ===================================================================
    // PAGE: PROFILE
    // ===================================================================
    async function renderProfile() {
        skeleton();
        const res = await api('profile.php', {});
        if (!res.ok) { app.innerHTML = errBox(res.message); return; }
        const p = res.data;
        app.className = 'app-main fade-in';
        const photo = p.photo_url || State.tgPhoto || '';
        const avatar = photo ? `style="background-image:url('${esc(photo)}')"` : '';
        const initial = esc((p.first_name || 'U').charAt(0).toUpperCase());
        const sup = p.support || {};
        app.innerHTML = `
            <div class="profile-head">
                <div class="profile-avatar fade-in" ${avatar}>${photo ? '' : initial}</div>
                <div class="profile-name">${esc(p.first_name || 'User')} ${esc(p.last_name || '')}</div>
                <div class="profile-username">${p.username ? '@' + esc(p.username) : ''}</div>
            </div>
            <div class="card-soft">
                <div class="profile-row"><span class="k">Telegram ID</span><span class="v">${esc(p.telegram_id)}</span></div>
                <div class="profile-row"><span class="k">Referral Code</span><span class="v">${esc(p.referral_code)}</span></div>
                <div class="profile-row"><span class="k">Language</span><span class="v">${esc((p.language || 'en').toUpperCase())}</span></div>
                <div class="profile-row" style="border:none"><span class="k">Joined</span><span class="v">${esc((p.created_at || '').split(' ')[0])}</span></div>
            </div>
            <div class="card-soft mt-12 profile-links">
                ${sup.support_username ? `<a id="supLink"><i class="bi bi-headset"></i> Support</a>` : ''}
                ${sup.privacy_url ? `<a href="${esc(sup.privacy_url)}" target="_blank"><i class="bi bi-shield-check"></i> Privacy Policy</a>` : ''}
                ${sup.terms_url ? `<a href="${esc(sup.terms_url)}" target="_blank"><i class="bi bi-file-text"></i> Terms of Service</a>` : ''}
                <a id="closeApp" style="border:none;color:var(--red)"><i class="bi bi-box-arrow-right" style="color:var(--red)"></i> Close App</a>
            </div>`;
        const sl = $('#supLink');
        if (sl) sl.addEventListener('click', () => { const u = 'https://t.me/' + sup.support_username.replace('@', ''); if (tg && tg.openTelegramLink) tg.openTelegramLink(u); else window.open(u, '_blank'); });
        $('#closeApp').addEventListener('click', () => { if (tg && tg.close) tg.close(); });
    }

    function errBox(msg) {
        return `<div class="empty"><i class="bi bi-exclamation-triangle"></i>${esc(msg || 'Could not load')}</div>
                <button class="btn-grad mt-12" onclick="location.reload()">Retry</button>`;
    }

    // ===================================================================
    // BOOT
    // ===================================================================
    async function boot() {
        if (window.MTASK.maintenance) {
            app.innerHTML = '<div class="empty"><i class="bi bi-tools"></i>Under maintenance. Please check back soon.</div>';
            return;
        }
        const res = await api('session.php', {});
        if (!res.ok) {
            app.innerHTML = `<div class="empty"><i class="bi bi-shield-lock"></i>${esc(res.message || 'Please open this app inside Telegram.')}</div>`;
            return;
        }
        State.user = res.data.user;
        State.settings = res.data.settings;
        State.recent = res.data.recent;

        // Populate side menu identity.
        $('#menuName').textContent = (State.user.first_name || 'User') + (State.user.last_name ? ' ' + State.user.last_name : '');
        $('#menuId').textContent = State.user.username ? '@' + State.user.username : 'ID ' + State.user.telegram_id;
        const menuPhoto = State.user.photo_url || State.tgPhoto;
        if (menuPhoto) {
            $('#menuAvatar').style.backgroundImage = `url('${menuPhoto}')`;
            $('#menuAvatar').style.backgroundSize = 'cover';
            $('#menuAvatar').style.backgroundPosition = 'center';
        } else {
            $('#menuAvatar').textContent = (State.user.first_name || 'U').charAt(0).toUpperCase();
        }
        if (State.settings.announcement) $('#notifDot').hidden = false;

        navigate('home');
    }

    boot();
})();
