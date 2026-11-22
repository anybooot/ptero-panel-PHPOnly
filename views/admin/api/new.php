<?php $this->extend('layouts.admin'); ?>

<div class="page-header">
    <div>
        <h1 class="page-title">Create Application API Key</h1>
        <p class="page-subtitle">Generate credentials for external automation and API integrations</p>
    </div>
    <div>
        <a href="/admin/api" class="btn btn-secondary btn-sm">Back to API Keys</a>
    </div>
</div>

<div class="card" style="max-width: 900px;">
    <div class="card-header">Key Configuration</div>
    <form action="/admin/api/new" method="POST">
        <?= \Pterodactyl\Core\View::csrf() ?>
        <div class="card-body">
            <div class="form-group">
                <label class="form-label" for="api-memo">Description (Memo)</label>
                <input type="text" id="api-memo" name="memo" class="form-control" required placeholder="e.g. WHMCS Billing Automation, Discord Bot">
            </div>

            <div class="form-group">
                <label class="form-label" for="api-ips">Allowed IPs (Optional, One per line)</label>
                <textarea id="api-ips" name="allowed_ips" class="form-control" rows="3" placeholder="Leave blank to allow any IP address to connect with this key..."></textarea>
            </div>

            <h3 style="font-size: 15px; font-weight: 600; color: #fff; margin: 24px 0 12px 0; border-bottom: 1px solid var(--border-color); padding-bottom: 8px;">
                Permissions Matrix
            </h3>

            <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 16px;">
                <div class="form-group">
                    <label class="form-label">Servers</label>
                    <select name="r_servers" class="form-control">
                        <option value="3" selected>Read &amp; Write</option>
                        <option value="1">Read Only</option>
                        <option value="0">None</option>
                    </select>
                </div>

                <div class="form-group">
                    <label class="form-label">Nodes</label>
                    <select name="r_nodes" class="form-control">
                        <option value="3" selected>Read &amp; Write</option>
                        <option value="1">Read Only</option>
                        <option value="0">None</option>
                    </select>
                </div>

                <div class="form-group">
                    <label class="form-label">Allocations</label>
                    <select name="r_allocations" class="form-control">
                        <option value="3" selected>Read &amp; Write</option>
                        <option value="1">Read Only</option>
                        <option value="0">None</option>
                    </select>
                </div>

                <div class="form-group">
                    <label class="form-label">Users</label>
                    <select name="r_users" class="form-control">
                        <option value="3" selected>Read &amp; Write</option>
                        <option value="1">Read Only</option>
                        <option value="0">None</option>
                    </select>
                </div>

                <div class="form-group">
                    <label class="form-label">Locations</label>
                    <select name="r_locations" class="form-control">
                        <option value="3" selected>Read &amp; Write</option>
                        <option value="1">Read Only</option>
                        <option value="0">None</option>
                    </select>
                </div>

                <div class="form-group">
                    <label class="form-label">Nests &amp; Eggs</label>
                    <select name="r_nests" class="form-control">
                        <option value="3" selected>Read &amp; Write</option>
                        <option value="1">Read Only</option>
                        <option value="0">None</option>
                    </select>
                </div>

                <div class="form-group">
                    <label class="form-label">Database Hosts</label>
                    <select name="r_database_hosts" class="form-control">
                        <option value="3" selected>Read &amp; Write</option>
                        <option value="1">Read Only</option>
                        <option value="0">None</option>
                    </select>
                </div>

                <div class="form-group">
                    <label class="form-label">Server Databases</label>
                    <select name="r_server_databases" class="form-control">
                        <option value="3" selected>Read &amp; Write</option>
                        <option value="1">Read Only</option>
                        <option value="0">None</option>
                    </select>
                </div>
            </div>
        </div>
        <div class="card-footer" style="text-align: right;">
            <button type="submit" class="btn btn-primary">Generate API Key</button>
        </div>
    </form>
</div>
