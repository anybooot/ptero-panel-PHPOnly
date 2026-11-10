/**
 * Pterodactyl Panel - Real-time HTML5 Canvas Resource Charts
 */

const ServerStats = (() => {
    let limits = { cpu: 100, memory: 1024 };
    const maxPoints = 30;

    const data = {
        cpu: new Array(maxPoints).fill(0),
        ram: new Array(maxPoints).fill(0),
        net: new Array(maxPoints).fill(0)
    };

    let charts = {};

    const init = (serverLimits) => {
        if (serverLimits) {
            limits = { ...limits, ...serverLimits };
        }

        setupChart('cpu', 'chart-cpu', '#3b82f6', 'rgba(59, 130, 246, 0.15)');
        setupChart('ram', 'chart-ram', '#10b981', 'rgba(16, 185, 129, 0.15)');
        setupChart('net', 'chart-net', '#f59e0b', 'rgba(245, 158, 11, 0.15)');

        // Handle canvas resize on window resize
        window.addEventListener('resize', () => {
            Object.values(charts).forEach(c => {
                resizeCanvas(c.canvas);
                drawChart(c);
            });
        });
    };

    const setupChart = (key, canvasId, strokeColor, fillColor) => {
        const canvas = document.getElementById(canvasId);
        if (!canvas) return;

        resizeCanvas(canvas);
        const ctx = canvas.getContext('2d');
        charts[key] = { key, canvas, ctx, strokeColor, fillColor };
        drawChart(charts[key]);
    };

    const resizeCanvas = (canvas) => {
        const rect = canvas.parentElement.getBoundingClientRect();
        canvas.width = rect.width * (window.devicePixelRatio || 1);
        canvas.height = rect.height * (window.devicePixelRatio || 1);
    };

    const update = (stats) => {
        if (!stats) return;

        // CPU
        const cpuPercent = parseFloat((stats.cpu_absolute || 0).toFixed(1));
        data.cpu.push(cpuPercent);
        if (data.cpu.length > maxPoints) data.cpu.shift();

        const cpuValEl = document.getElementById('stat-cpu-val');
        if (cpuValEl) cpuValEl.textContent = `${cpuPercent}%`;

        // RAM
        const ramBytes = stats.memory_bytes || 0;
        const ramMiB = Math.round(ramBytes / 1024 / 1024);
        data.ram.push(ramMiB);
        if (data.ram.length > maxPoints) data.ram.shift();

        const ramValEl = document.getElementById('stat-ram-val');
        if (ramValEl) {
            if (ramMiB >= 1024) {
                ramValEl.textContent = `${(ramMiB / 1024).toFixed(2)} GiB`;
            } else {
                ramValEl.textContent = `${ramMiB} MiB`;
            }
        }

        // Network
        const netRx = stats.network?.rx_bytes || 0;
        const netTx = stats.network?.tx_bytes || 0;
        const netTotalKiB = Math.round((netRx + netTx) / 1024);
        data.net.push(netTotalKiB);
        if (data.net.length > maxPoints) data.net.shift();

        const netValEl = document.getElementById('stat-net-val');
        if (netValEl) {
            if (netTotalKiB >= 1024) {
                netValEl.textContent = `${(netTotalKiB / 1024).toFixed(1)} MiB`;
            } else {
                netValEl.textContent = `${netTotalKiB} KiB`;
            }
        }

        // Re-draw canvases
        if (charts.cpu) drawChart(charts.cpu, limits.cpu > 0 ? limits.cpu : Math.max(...data.cpu, 100));
        if (charts.ram) drawChart(charts.ram, limits.memory > 0 ? limits.memory : Math.max(...data.ram, 512));
        if (charts.net) drawChart(charts.net, Math.max(...data.net, 50));
    };

    const drawChart = (chart, maxVal = 100) => {
        const { key, canvas, ctx, strokeColor, fillColor } = chart;
        const points = data[key];
        const w = canvas.width;
        const h = canvas.height;

        ctx.clearRect(0, 0, w, h);

        if (points.length < 2) return;

        const step = w / (maxPoints - 1);
        const effectiveMax = Math.max(maxVal, 1);

        ctx.beginPath();
        points.forEach((val, i) => {
            const x = i * step;
            const y = h - ((val / effectiveMax) * (h * 0.85)) - 4;
            if (i === 0) {
                ctx.moveTo(x, y);
            } else {
                ctx.lineTo(x, y);
            }
        });

        // Stroke line
        ctx.strokeStyle = strokeColor;
        ctx.lineWidth = 2 * (window.devicePixelRatio || 1);
        ctx.lineJoin = 'round';
        ctx.lineCap = 'round';
        ctx.stroke();

        // Fill area underneath
        ctx.lineTo(w, h);
        ctx.lineTo(0, h);
        ctx.closePath();
        ctx.fillStyle = fillColor;
        ctx.fill();
    };

    return {
        init,
        update
    };
})();

window.ServerStats = ServerStats;
