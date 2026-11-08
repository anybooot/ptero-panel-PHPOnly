<?php $this->extend('layouts.server'); ?>

<div class="page-header" style="margin-bottom: 16px;">
    <div>
        <h1 class="page-title">Schedules</h1>
        <p class="page-subtitle">Configure automated tasks and cron jobs for this server</p>
    </div>
    <div>
        <button class="btn btn-primary btn-sm" onclick="App.openModal('modal-create-schedule')">Create Schedule</button>
    </div>
</div>

<div class="card">
    <div class="card-body" style="padding: 0;">
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>Schedule</th>
                        <th>Cron Expression</th>
                        <th>Status</th>
                        <th>Tasks</th>
                        <th>Last Run</th>
                        <th style="text-align: right;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($schedules)): ?>
                        <tr><td colspan="6" style="text-align: center; color: var(--text-muted); padding: 24px;">No schedules have been configured for this server.</td></tr>
                    <?php else: ?>
                        <?php foreach ($schedules as $s): ?>
                            <tr>
                                <td>
                                    <a href="/server/<?= $server['uuidShort'] ?>/schedules/<?= $s['id'] ?>" style="font-weight: 600; color: #fff;">
                                        <?= \Pterodactyl\Core\View::e($s['name']) ?>
                                    </a>
                                </td>
                                <td style="font-family: var(--font-mono); color: #93c5fd;">
                                    <?= "{$s['cron_minute']} {$s['cron_hour']} {$s['cron_day_of_month']} {$s['cron_month']} {$s['cron_day_of_week']}" ?>
                                </td>
                                <td>
                                    <span class="badge <?= !empty($s['is_active']) ? 'badge-success' : 'badge-secondary' ?>">
                                        <?= !empty($s['is_active']) ? 'Active' : 'Inactive' ?>
                                    </span>
                                </td>
                                <td><?= (int)$s['tasks_count'] ?> task(s)</td>
                                <td style="color: var(--text-muted); font-size: 12px;"><?= $s['last_run_at'] ? date('M j, Y H:i', strtotime($s['last_run_at'])) : 'Never' ?></td>
                                <td style="text-align: right; white-space: nowrap;">
                                    <button class="btn btn-secondary btn-sm" onclick="executeSchedule('<?= $s['id'] ?>')">Run Now</button>
                                    <a href="/server/<?= $server['uuidShort'] ?>/schedules/<?= $s['id'] ?>" class="btn btn-secondary btn-sm">Edit</a>
                                    <button class="btn btn-danger btn-sm" onclick="deleteSchedule('<?= $s['id'] ?>')">Delete</button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Create Schedule Modal -->
<div class="modal-backdrop" id="modal-create-schedule">
    <div class="modal-dialog">
        <div class="modal-header">
            <h3 class="modal-title">Create Schedule</h3>
            <button class="modal-close" data-modal-close>&times;</button>
        </div>
        <form id="create-schedule-form">
            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label" for="sched-name">Schedule Name</label>
                    <input type="text" id="sched-name" class="form-control" required placeholder="e.g. Daily Restart, Hourly Backup">
                </div>
                <div style="display: grid; grid-template-columns: repeat(5, 1fr); gap: 8px;">
                    <div class="form-group">
                        <label class="form-label">Minute</label>
                        <input type="text" id="cron-minute" class="form-control" value="*">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Hour</label>
                        <input type="text" id="cron-hour" class="form-control" value="*">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Day (Month)</label>
                        <input type="text" id="cron-dom" class="form-control" value="*">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Month</label>
                        <input type="text" id="cron-month" class="form-control" value="*">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Day (Week)</label>
                        <input type="text" id="cron-dow" class="form-control" value="*">
                    </div>
                </div>
                <div style="display: flex; gap: 16px; margin-top: 8px;">
                    <label style="display: flex; align-items: center; gap: 6px; font-size: 13px; color: #fff;">
                        <input type="checkbox" id="sched-active" checked> Is Active
                    </label>
                    <label style="display: flex; align-items: center; gap: 6px; font-size: 13px; color: #fff;">
                        <input type="checkbox" id="sched-online"> Only when server is online
                    </label>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-modal-close>Cancel</button>
                <button type="submit" class="btn btn-primary">Create Schedule</button>
            </div>
        </form>
    </div>
</div>

<script>
document.getElementById('create-schedule-form').addEventListener('submit', async (e) => {
    e.preventDefault();
    const name = document.getElementById('sched-name').value;
    const cron_minute = document.getElementById('cron-minute').value;
    const cron_hour = document.getElementById('cron-hour').value;
    const cron_day_of_month = document.getElementById('cron-dom').value;
    const cron_month = document.getElementById('cron-month').value;
    const cron_day_of_week = document.getElementById('cron-dow').value;
    const is_active = document.getElementById('sched-active').checked;
    const only_when_online = document.getElementById('sched-online').checked;

    try {
        const res = await App.request('/api/client/servers/<?= $server['uuid'] ?>/schedules', {
            method: 'POST',
            body: { name, cron_minute, cron_hour, cron_day_of_month, cron_month, cron_day_of_week, is_active, only_when_online }
        });
        App.toast('Schedule created successfully.', 'success');
        setTimeout(() => window.location.href = `/server/<?= $server['uuidShort'] ?>/schedules/${res.id}`, 600);
    } catch (e) {}
});

async function executeSchedule(id) {
    try {
        await App.request(`/api/client/servers/<?= $server['uuid'] ?>/schedules/${id}/execute`, { method: 'POST' });
        App.toast('Schedule execution queued.', 'success');
    } catch (e) {}
}

async function deleteSchedule(id) {
    if (!confirm('Are you sure you want to delete this schedule? All configured tasks will be removed.')) return;
    try {
        await App.request(`/api/client/servers/<?= $server['uuid'] ?>/schedules/${id}`, { method: 'DELETE' });
        App.toast('Schedule deleted.', 'success');
        setTimeout(() => window.location.reload(), 600);
    } catch (e) {}
}
</script>
