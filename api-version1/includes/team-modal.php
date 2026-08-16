<?php
$_team = [
    'System Administrator' => [
        ['name' => 'Stefhanie Ann V. Batucan',  'initials' => 'SB', 'year' => '4th Year'],
        ['name' => 'Jullan Carl J. Maglinte',   'initials' => 'JM', 'year' => '2nd Year'],
    ],
    'Frontend Developer' => [
        ['name' => 'Marc Lester D. Guido',  'initials' => 'MG', 'year' => '2nd Year'],
        ['name' => 'Justine P. Buncag',     'initials' => 'JB', 'year' => '2nd Year'],
        ['name' => 'Christoph B. Bagabuyo', 'initials' => 'CB', 'year' => '2nd Year'],
    ],
    'Backend Developer' => [
        ['name' => 'Kenzen L. Miñao',        'initials' => 'KM', 'year' => '2nd Year'],
        ['name' => 'Keith Brian Laranjo',    'initials' => 'KL', 'year' => '3rd Year'],
        ['name' => 'Ej Abrasaldo Vinculado', 'initials' => 'EV', 'year' => '4th Year'],
        ['name' => 'Japhet Bastillada', 'initials' => 'JB', 'year' => '2nd Year'],
    ],
    'UI/UX Designer' => [
        ['name' => 'Japhet Bastillada', 'initials' => 'JB', 'year' => '2nd Year'],
    ],
];
$_avatarColors = ['#0d1b3e','#1a3a8f','#f5c400','#16a34a','#7c3aed','#dc2626','#0891b2','#d97706'];
$_ci = 0;
?>
<style>
.tm-overlay {
    display: none;
    position: fixed;
    inset: 0;
    background: rgba(0,0,0,.6);
    z-index: 10000;
    align-items: center;
    justify-content: center;
    padding: 20px;
}
.tm-overlay.open { display: flex; animation: tmFade .2s ease; }
@keyframes tmFade { from{opacity:0} to{opacity:1} }
.tm-modal {
    background: #fff;
    border-radius: 20px;
    width: 100%;
    max-width: 720px;
    max-height: 88vh;
    overflow: hidden;
    display: flex;
    flex-direction: column;
    box-shadow: 0 32px 80px rgba(0,0,0,.28);
    animation: tmSlide .22s ease;
}
@keyframes tmSlide { from{opacity:0;transform:translateY(20px)} to{opacity:1;transform:translateY(0)} }
.tm-header {
    background: linear-gradient(135deg, #0d1b3e 0%, #1a3a8f 100%);
    padding: 28px 32px 22px;
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    flex-shrink: 0;
}
.tm-header-text {}
.tm-header h2 {
    margin: 0 0 4px;
    font-size: 20px;
    font-weight: 900;
    color: #fff;
    letter-spacing: -.3px;
}
.tm-header p {
    margin: 0;
    font-size: 13px;
    color: rgba(255,255,255,.65);
    font-weight: 500;
}
.tm-close {
    background: rgba(255,255,255,.15);
    border: none;
    color: #fff;
    width: 34px;
    height: 34px;
    border-radius: 50%;
    cursor: pointer;
    font-size: 18px;
    line-height: 1;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
    transition: background .15s;
}
.tm-close:hover { background: rgba(255,255,255,.28); }
.tm-body {
    overflow-y: auto;
    padding: 28px 32px 32px;
    display: flex;
    flex-direction: column;
    gap: 28px;
}
.tm-role-block {}
.tm-role-title {
    font-size: 11px;
    font-weight: 800;
    color: #1a3a8f;
    text-transform: uppercase;
    letter-spacing: 1.2px;
    margin-bottom: 14px;
    padding-bottom: 8px;
    border-bottom: 2px solid #e5e7eb;
}
.tm-members {
    display: flex;
    flex-wrap: wrap;
    gap: 12px;
}
.tm-member {
    display: flex;
    align-items: center;
    gap: 13px;
    background: #f9fafb;
    border: 1px solid #e5e7eb;
    border-radius: 12px;
    padding: 12px 16px;
    flex: 1 1 220px;
    min-width: 0;
}
.tm-avatar {
    width: 46px;
    height: 46px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 15px;
    font-weight: 900;
    color: #fff;
    flex-shrink: 0;
    letter-spacing: -.5px;
}
.tm-info {}
.tm-name {
    font-size: 13px;
    font-weight: 800;
    color: #111827;
    margin-bottom: 2px;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
.tm-course {
    font-size: 11px;
    color: #9ca3af;
    font-weight: 500;
}
@media (max-width: 520px) {
    .tm-header { padding: 20px 20px 16px; }
    .tm-body { padding: 20px 20px 24px; gap: 22px; }
    .tm-member { flex: 1 1 100%; }
}
</style>

<div class="tm-overlay" id="teamModal" onclick="if(event.target===this)closeTeamModal()">
    <div class="tm-modal">
        <div class="tm-header">
            <div class="tm-header-text">
                <h2>CCS-Creatives Society</h2>
                <p>The team behind the JRMSU SSG E-Ballot Portal</p>
            </div>
            <button class="tm-close" onclick="closeTeamModal()" aria-label="Close">&times;</button>
        </div>
        <div class="tm-body">
            <?php foreach ($_team as $_role => $_members): ?>
            <div class="tm-role-block">
                <div class="tm-role-title"><?= htmlspecialchars($_role) ?></div>
                <div class="tm-members">
                    <?php foreach ($_members as $_m):
                        $clr = $_avatarColors[$_ci % count($_avatarColors)]; $_ci++;
                    ?>
                    <div class="tm-member">
                        <div class="tm-avatar" style="background:<?= $clr ?>">
                            <?= htmlspecialchars($_m['initials']) ?>
                        </div>
                        <div class="tm-info">
                            <div class="tm-name"><?= htmlspecialchars($_m['name']) ?></div>
                            <div class="tm-course">Bachelor of Science in Computer Science &bull; <?= htmlspecialchars($_m['year']) ?></div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<script>
function openTeamModal()  { document.getElementById('teamModal').classList.add('open'); }
function closeTeamModal() { document.getElementById('teamModal').classList.remove('open'); }
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') closeTeamModal();
});
</script>
