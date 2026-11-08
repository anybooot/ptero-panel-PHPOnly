<?php $this->extend('layouts.server'); ?>

<div class="page-header" style="margin-bottom: 16px;">
    <div>
        <h1 class="page-title">Edit Schedule: <?= \Pterodactyl\Core\View::e($schedule['name']) ?></h1>
        <p class="page-subtitle">Configure cron settings and automated action sequences</p>
    </div>
    <div style="display: flex; gap: 8px;">
        <a href="/server/<?= $server['uuidShort'] ?>/schedules" class="btn btn-secondary btn-sm">Back to Schedules</a>
        <button class="btn btn-secondary btn-sm" onclick="executeSchedule('<?= $schedule['id'] ?>')">Run Now</button>
        <button class="btn btn-primary btn-sm" onclick="App.openModal('modal-add-task')">Add Task</button>
    </div>
</div>

<!-- Tasks Sequence Card -->
<div class="card">
    <div class="card-header">Configured Tasks</div>
    <div class="card-body" style="padding: 0;">
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th style="width: 60px;">Step</th>
                        <th>Action</th>
                        <th>Payload</th>
                        <th>Time Offset</th>
                        <th style="text-align: right;">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($tasks)): ?>
                        <tr><td colspan="5" style="text-align: center; color: var(--text-muted); padding: 24px;">No tasks have been added to this schedule. Click "Add Task" to create one.</td></tr>
                    <?php else: ?>
                        <?php foreach ($tasks as $t): ?>
                            <tr>
                                <td style="font-weight: 700; color: #fff; text-align: center;"><?= (int)$t['sequence_id'] ?></td>
                                <td>
                                    <span class="badge badge-secondary"><?= strtoupper(\Pterodactyl\Core\View::e($t['action'])) ?></span>
                                </td>
                                <td style="font-family: var(--font-mono); font-size: 13px;"><?= \Pterodactyl\Core\View::e($t['payload']) ?></td>
                                <td><?= (int)$t['time_offset'] ?>s</td>
                                <td style="text-align: right;">
                                    <button class="btn btn-danger btn-sm" onclick="deleteTask('<?= $t['id'] ?>')">Delete</button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Add Task Modal -->
<div class="modal-backdrop" id="modal-add-task">
    <div class="modal-dialog">
        <div class="modal-header">
            <h3 class="modal-title">Add Schedule Task</h3>
            <button class="modal-close" data-modal-close>&times;</button>
        </div>
        <form id="add-task-form">
            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label" for="task-action">Action</label>
                    <select id="task-action" class="form-control">
                        <option value="command">Send Console Command</option>
                        <option value="power">Send Power Action</option>
                        <option value="backup">Create Backup</option>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label" for="task-payload">Payload / Command</label>
                    <input type="text" id="task-payload" class="form-control" placeholder="e.g. say Server restarting in 60s, or restart, stop">
                </div>
                <div class="form-group">
                    <label class="form-label" for="task-offset">Time Offset (Seconds after previous task)</label>
                    <input type="number" id="task-offset" class="form-control" value="0" min="0">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-modal-close>Cancel</button>
                <button type="submit" class="btn btn-primary">Add Task</button>
            </div>
        </form>
    </div>
</div>

<script>
document.getElementById('add-task-form').addEventListener('submit', async (e) => {
    e.preventDefault();
    const action = document.getElementById('task-action').value;
    const payload = document.getElementById('task-payload').value;
    const time_offset = parseInt(document.getElementById('task-offset').value || '0', 10);

    try {
        await App.request('/api/client/servers/<?= $server['uuid'] ?>/schedules/<?= $schedule['id'] ?>/tasks', {
            method: 'POST',
            body: { action, payload, time_offset }
        });
        App.toast('Task added.', 'success');
        setTimeout(() => window.location.reload(), 600);
    } catch (e) {}
});

async function deleteTask(taskId) {
    if (!confirm('Are you sure you want to remove this task?')) return;
    try {
        await App.request(`/api/client/servers/<?= $server['uuid'] ?>/schedules/<?= $schedule['id'] ?>/tasks/${taskId}`, {
            method: 'DELETE'
        });
        App.toast('Task removed.', 'success');
        setTimeout(() => window.location.reload(), 600);
    } catch (e) {}
}

async function executeSchedule(id) {
    try {
        await App.request(`/api/client/servers/<?= $server['uuid'] ?>/schedules/${id}/execute`, { method: 'POST' });
        App.toast('Schedule executed.', 'success');
    } catch (e) {}
}
</script>
