// MODERNIZAÇÃO: Gráfico de Previsão de Gastos com visualização por período (semanal/mensal/anual)
let forecastChartInstance = null;

function initForecastChart(period = 'monthly') {
    const forecastElement = document.getElementById('chartForecast');
    if (!forecastElement || typeof extendedHistoryData === 'undefined' || !Array.isArray(extendedHistoryData) || extendedHistoryData.length === 0) {
        return;
    }

    // Destrói instância anterior se existir
    if (forecastChartInstance) {
        forecastChartInstance.destroy();
        forecastChartInstance = null;
    }

    // Paleta resolvida dos tokens (chart-tokens.js); relida a cada rebuild, então
    // o gráfico segue o tema. Fallback defensivo se o helper não carregou.
    const P = (window.NVXCharts && window.NVXCharts.palette)
        ? window.NVXCharts.palette()
        : {
            ink: { hex: '#E9EFF8', a: () => '#E9EFF8' },
            muted: { hex: '#8891a8', a: () => '#8891a8' },
            accent: { hex: '#8803FC', a: () => '#8803FC' },
            pos: { hex: '#2E7D5B', a: () => '#2E7D5B' },
            neg: { hex: '#B5433A', a: () => '#B5433A' },
            gold: { hex: '#B07C28', a: () => '#B07C28' },
            surface: { hex: '#111B30', a: () => '#111B30' }
        };

    const monthNames = ['Jan', 'Fev', 'Mar', 'Abr', 'Mai', 'Jun', 'Jul', 'Ago', 'Set', 'Out', 'Nov', 'Dez'];
    const last6 = extendedHistoryData.slice(-6);

    if (last6.length === 0) {
        return;
    }

    const historicalExpenses = last6.map(item => Number(item.expense) || 0);
    const historicalLength = historicalExpenses.length;
    const lastHistoricalValue = historicalExpenses[historicalLength - 1];
    
    // Prepara dados baseado no período selecionado
    let labels = [];
    let historicalForChart = [];
    let projectionForChart = [];
    let confidenceUpper = [];
    let confidenceLower = [];
    let datasets = [];

    // Histórico sempre presente
    const historicalLabels = last6.map(item => {
        const period = item.month || item.period;
        const [year, month] = period.split('-');
        return `${monthNames[parseInt(month) - 1]}/${year.substring(2)}`;
    });
    const historicalData = historicalExpenses.map(v => v);

    if (period === 'weekly') {
        // Visualização semanal - mostra histórico mensal + previsões semanais para as próximas 4 semanas
        const hasWeeklyProjections = typeof weeklyProjectionData !== 'undefined' && Array.isArray(weeklyProjectionData) && weeklyProjectionData.length > 0;
        
        if (hasWeeklyProjections) {
            const weeklyLabels = weeklyProjectionData.map(item => item.week_label || `Sem ${item.week}`);
            const weeklyExpenses = weeklyProjectionData.map(item => Number(item.expense) || 0);
            
            labels = [...historicalLabels, ...weeklyLabels];
            historicalForChart = [...historicalData, ...Array(weeklyLabels.length).fill(null)];
            projectionForChart = [
                ...Array(historicalLength - 1).fill(null),
                lastHistoricalValue, // Conecta com o histórico
                ...weeklyExpenses    // Previsões semanais para próximas 4 semanas
            ];
            
            confidenceUpper = [
                ...Array(historicalLength - 1).fill(null),
                lastHistoricalValue * 1.2,
                ...weeklyExpenses.map(v => v * 1.2)
            ];
            confidenceLower = [
                ...Array(historicalLength - 1).fill(null),
                lastHistoricalValue * 0.8,
                ...weeklyExpenses.map(v => v * 0.8)
            ];
            
            datasets.push({
                label: 'Previsão Semanal (próximas 4 semanas)',
                data: projectionForChart,
                borderColor: P.gold.a(0.9),
                backgroundColor: P.gold.a(0.03),
                tension: 0.98, // Curvas extremamente suaves, elegantes e delicadas
                borderWidth: 2, // Linha delicada e elegante
                borderDash: [5, 3.5],
                pointRadius: 3,
                pointHoverRadius: 5.5,
                pointBackgroundColor: P.gold.a(0.9),
                pointBorderColor: P.surface.hex,
                pointBorderWidth: 1.5,
                pointHoverBorderWidth: 2,
                fill: false,
                spanGaps: true,
                order: 1,
            });
        } else {
            // Se não houver dados semanais, mostra apenas histórico mensal
            labels = historicalLabels;
            historicalForChart = historicalData;
        }
    } else if (period === 'monthly') {
        // Visualização mensal - histórico + projeções mensais
        if (typeof projectionData !== 'undefined' && Array.isArray(projectionData) && projectionData.length > 0) {
            const projectedLabels = projectionData.map(item => {
                const [year, month] = item.month.split('-');
                return `${monthNames[parseInt(month) - 1]}/${year.substring(2)} (proj.)`;
            });
            const projectedExpenses = projectionData.map(item => Number(item.expense) || 0);
            
            labels = [...historicalLabels, ...projectedLabels];
            historicalForChart = [...historicalData, ...Array(projectedExpenses.length).fill(null)];
            projectionForChart = [
                ...Array(historicalLength - 1).fill(null),
                lastHistoricalValue,
                ...projectedExpenses
            ];
            
            confidenceUpper = [
                ...Array(historicalLength - 1).fill(null),
                lastHistoricalValue * 1.2,
                ...projectedExpenses.map(v => v * 1.2)
            ];
            confidenceLower = [
                ...Array(historicalLength - 1).fill(null),
                lastHistoricalValue * 0.8,
                ...projectedExpenses.map(v => v * 0.8)
            ];
            
            datasets.push({
                label: 'Projeção Mensal',
                data: projectionForChart,
                borderColor: P.accent.a(0.85),
                backgroundColor: P.accent.a(0),
                tension: 0.98, // Curvas extremamente suaves, elegantes e delicadas
                borderWidth: 2, // Linha delicada e elegante
                borderDash: [6, 3.5],
                pointRadius: 3,
                pointHoverRadius: 5.5,
                pointBackgroundColor: P.accent.a(0.85),
                pointBorderColor: P.surface.hex,
                pointBorderWidth: 1.5,
                pointHoverBorderWidth: 2,
                fill: false,
                spanGaps: true,
                order: 2,
            });
        } else {
            labels = historicalLabels;
            historicalForChart = historicalData;
        }
    } else if (period === 'yearly') {
        // Visualização anual - agrupa dados mensais por ano
        const yearlyData = {};
        last6.forEach(item => {
            const period = item.month || item.period;
            const year = period.split('-')[0];
            if (!yearlyData[year]) {
                yearlyData[year] = 0;
            }
            yearlyData[year] += Number(item.expense) || 0;
        });
        
        labels = Object.keys(yearlyData).sort();
        historicalForChart = labels.map(year => yearlyData[year]);
        
        // Projeção anual (média anual dos últimos anos * 12 meses)
        if (labels.length > 0) {
            const avgYearly = historicalForChart.reduce((a, b) => a + b, 0) / historicalForChart.length;
            labels.push(`${parseInt(labels[labels.length - 1]) + 1} (proj.)`);
            historicalForChart.push(null);
            projectionForChart = [
                ...Array(labels.length - 1).fill(null),
                avgYearly
            ];
            
            // Inicializa faixa de confiança para modo anual
            confidenceUpper = [
                ...Array(labels.length - 1).fill(null),
                avgYearly * 1.2
            ];
            confidenceLower = [
                ...Array(labels.length - 1).fill(null),
                avgYearly * 0.8
            ];
            
            datasets.push({
                label: 'Projeção Anual',
                data: projectionForChart,
                borderColor: P.accent.a(0.85),
                backgroundColor: P.accent.a(0),
                tension: 0.98, // Curvas extremamente suaves, elegantes e delicadas
                borderWidth: 2, // Linha delicada e elegante
                borderDash: [6, 3.5],
                pointRadius: 3,
                pointHoverRadius: 5.5,
                pointBackgroundColor: P.accent.a(0.85),
                pointBorderColor: P.surface.hex,
                pointBorderWidth: 1.5,
                pointHoverBorderWidth: 2,
                fill: false,
                spanGaps: true,
                order: 2,
            });
        } else {
            // Garante que arrays estejam inicializados mesmo se não houver dados
            confidenceUpper = [];
            confidenceLower = [];
            projectionForChart = [];
        }
    }

    // Garante que todos os arrays têm o mesmo tamanho
    const targetLength = labels.length;
    while (historicalForChart.length < targetLength) historicalForChart.push(null);
    while (projectionForChart.length < targetLength) projectionForChart.push(null);
    // Inicializa confidenceUpper e confidenceLower se ainda não foram inicializados
    if (!Array.isArray(confidenceUpper)) confidenceUpper = [];
    if (!Array.isArray(confidenceLower)) confidenceLower = [];
    while (confidenceUpper.length < targetLength) confidenceUpper.push(null);
    while (confidenceLower.length < targetLength) confidenceLower.push(null);
    
    historicalForChart.length = targetLength;
    projectionForChart.length = targetLength;
    confidenceUpper.length = targetLength;
    confidenceLower.length = targetLength;

    // Gradientes elegantes e suaves com múltiplas paradas para transições mais refinadas
    const ctx = forecastElement.getContext('2d');
    const confidenceGradient = ctx.createLinearGradient(0, 0, 0, forecastElement.height || 380);
    confidenceGradient.addColorStop(0, P.accent.a(0.28));
    confidenceGradient.addColorStop(0.2, P.accent.a(0.15));
    confidenceGradient.addColorStop(0.4, P.accent.a(0.1));
    confidenceGradient.addColorStop(0.6, P.accent.a(0.06));
    confidenceGradient.addColorStop(0.8, P.accent.a(0.03));
    confidenceGradient.addColorStop(1, P.accent.a(0.01));
    
    const historicalGradient = ctx.createLinearGradient(0, 0, 0, forecastElement.height || 380);
    historicalGradient.addColorStop(0, P.neg.a(0.26));
    historicalGradient.addColorStop(0.2, P.neg.a(0.16));
    historicalGradient.addColorStop(0.4, P.neg.a(0.1));
    historicalGradient.addColorStop(0.6, P.neg.a(0.06));
    historicalGradient.addColorStop(0.8, P.neg.a(0.03));
    historicalGradient.addColorStop(1, P.neg.a(0.01));

    // Adiciona faixa de confiança se houver projeções
    if (confidenceUpper.filter(v => v !== null).length > 0) {
        datasets.unshift(
            {
                label: 'Limite superior (±20%)',
                data: confidenceUpper,
                borderColor: P.accent.a(0.45),
                backgroundColor: P.accent.a(0),
                fill: false,
                pointRadius: 0,
                pointHoverRadius: 0,
                borderWidth: 0.8,
                borderDash: [2, 2],
                tension: 0.95,
                order: 4,
                spanGaps: false,
            },
            {
                label: 'Limite inferior',
                data: confidenceLower,
                borderColor: P.accent.a(0.45),
                backgroundColor: confidenceGradient,
                fill: '-1',
                pointRadius: 0,
                pointHoverRadius: 0,
                borderWidth: 0.8,
                borderDash: [2, 2],
                tension: 0.95,
                order: 3,
                spanGaps: false,
            }
        );
    }

    // Adiciona histórico
    datasets.push({
        label: 'Histórico',
        data: historicalForChart,
        borderColor: P.neg.a(0.9),
        backgroundColor: historicalGradient,
        tension: 0.98, // Curvas extremamente suaves, elegantes e delicadas
        borderWidth: 2, // Linha delicada e elegante
        pointRadius: 3,
        pointHoverRadius: 5.5,
        pointBackgroundColor: P.neg.a(0.9),
        pointBorderColor: P.surface.hex,
        pointBorderWidth: 1.5,
        pointHoverBorderWidth: 2,
        fill: true,
        order: 3,
        spanGaps: false,
    });

    forecastChartInstance = new Chart(forecastElement, {
        type: 'line',
        data: { labels, datasets },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            animation: {
                duration: 1400,
                easing: 'easeInOutCubic'
            },
            interaction: {
                mode: 'index',
                intersect: false,
            },
            scales: {
                x: {
                    ticks: { 
                        color: P.muted.hex, 
                        font: { size: 11, weight: '400' }
                    },
                    grid: { 
                        color: P.muted.a(0.12),
                        lineWidth: 1
                    },
                },
                y: {
                    beginAtZero: true,
                    ticks: {
                        color: P.muted.hex,
                        font: { size: 11, weight: '400' },
                        callback: (value) => 'R$ ' + value.toLocaleString('pt-BR', { minimumFractionDigits: 0, maximumFractionDigits: 0 }),
                    },
                    grid: { 
                        color: P.muted.a(0.12),
                        lineWidth: 1
                    },
                },
            },
            plugins: {
                legend: {
                    display: true,
                    position: 'top',
                    labels: {
                        color: P.muted.hex,
                        usePointStyle: true,
                        padding: 10,
                        font: { size: 11, weight: '400' },
                        boxWidth: 7,
                        boxHeight: 7,
                        filter: (item) => item.text !== 'Limite superior (±20%)' && item.text !== 'Limite inferior',
                    },
                },
                tooltip: {
                    backgroundColor: P.ink.a(0.95),
                    titleColor: P.surface.hex,
                    bodyColor: P.surface.a(0.82),
                    borderColor: P.accent.a(0.4),
                    borderWidth: 1,
                    padding: 14,
                    cornerRadius: 10,
                    displayColors: true,
                    boxPadding: 6,
                    callbacks: {
                        label: (context) => {
                            const value = context.parsed.y;
                            if (value === null || value === undefined || isNaN(value)) return null;
                            return `${context.dataset.label}: R$ ${value.toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
                        },
                    },
                },
            },
        },
    });
}

// MODERNIZAÇÃO: Função global para mudar período (chamada pelos botões)
function setForecastPeriodButtonState(period) {
    document.querySelectorAll('.forecast-period-btn').forEach(btn => {
        const btnPeriod = btn.getAttribute('data-period');
        const isActive = btnPeriod === period;

        btn.setAttribute('aria-pressed', isActive ? 'true' : 'false');

        if (isActive) {
            btn.classList.add('bg-violet-500/20', 'text-violet-200', 'border', 'border-violet-400/40');
            btn.classList.remove('text-slate-400', 'hover:text-white', 'hover:bg-slate-700/50');
        } else {
            btn.classList.remove('bg-violet-500/20', 'text-violet-200', 'border', 'border-violet-400/40');
            btn.classList.add('text-slate-400', 'hover:text-white', 'hover:bg-slate-700/50');
        }
    });
}

window.changeForecastPeriod = function(period) {
    // Atualiza botões ativos
    setForecastPeriodButtonState(period);
    
    // Recria o gráfico com novo período
    initForecastChart(period);
    
    // Salva preferência
    localStorage.setItem('forecastPeriod', period);
};

// Inicializa quando DOM estiver pronto
document.addEventListener('DOMContentLoaded', () => {
    if (document.getElementById('chartForecast')) {
        const savedPeriod = localStorage.getItem('forecastPeriod') || 'monthly';
        initForecastChart(savedPeriod);
        
        // Atualiza botão inicial
        setForecastPeriodButtonState(savedPeriod);
    }
});
