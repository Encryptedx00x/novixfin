document.addEventListener('DOMContentLoaded', () => {
    if (typeof Chart === 'undefined') {
        return;
    }

    // Paleta resolvida dos tokens dos 15 temas (chart-tokens.js). Lida no build,
    // então cada tema carrega com as cores certas nos gráficos. Fallback defensivo
    // caso o helper não tenha carregado.
    const readIsLight = () => {
        // Fallback defensivo (só se chart-tokens.js não carregou): decide
        // claro/escuro pela luminância do token --bg, igual ao helper canônico.
        try {
            const bg = getComputedStyle(document.documentElement).getPropertyValue('--bg').trim();
            const h = bg.charAt(0) === '#' ? bg.slice(1) : '';
            if (h.length >= 6) {
                const r = parseInt(h.slice(0, 2), 16);
                const g = parseInt(h.slice(2, 4), 16);
                const b = parseInt(h.slice(4, 6), 16);
                return (0.299 * r + 0.587 * g + 0.114 * b) / 255 > 0.5;
            }
        } catch (e) { /* mantém escuro por padrão */ }
        return false;
    };
    const P = (window.NVXCharts && window.NVXCharts.palette)
        ? window.NVXCharts.palette()
        : {
            isLight: readIsLight(),
            ink: { hex: '#E9EFF8', a: () => '#E9EFF8' },
            muted: { hex: '#8891a8', a: () => '#8891a8' },
            accent: { hex: '#8803FC', a: () => '#8803FC' },
            accent2: { hex: '#6C4FA8', a: () => '#6C4FA8' },
            pos: { hex: '#2E7D5B', a: () => '#2E7D5B' },
            neg: { hex: '#B5433A', a: () => '#B5433A' },
            gold: { hex: '#B07C28', a: () => '#B07C28' },
            surface: { hex: '#111B30', a: () => '#111B30' }
        };

    const dashboardChartTheme = {
        // Legenda das pizzas usa o texto secundário; a borda entre fatias usa a
        // cor de superfície (o "fundo" do card), em qualquer tema.
        pieLegendColor: P.muted.hex,
        pieBorderColor: P.surface.hex,
    };

    window.NOVIX_CATEGORY_OPTIONS = Array.isArray(window.NOVIX_CATEGORY_OPTIONS)
        ? window.NOVIX_CATEGORY_OPTIONS
        : [];

    function normalizeCategoryOption(option) {
        const id = String(option.id ?? option.value ?? '').trim();
        const name = String(option.name ?? option.text ?? '').trim();
        const type = String(option.type ?? '').trim();

        if (!id || !name || !['income', 'expense'].includes(type)) {
            return null;
        }

        return { id, name, type };
    }

    function appendCategoryOption(option) {
        const normalized = normalizeCategoryOption(option);
        if (!normalized) {
            return null;
        }

        const existing = window.NOVIX_CATEGORY_OPTIONS.find((item) => String(item.id) === normalized.id);
        if (existing) {
            existing.name = normalized.name;
            existing.type = normalized.type;
            return existing;
        }

        window.NOVIX_CATEGORY_OPTIONS.push(normalized);
        return normalized;
    }

    document.querySelectorAll('select[name="category_id"] option').forEach((option) => {
        appendCategoryOption({
            id: option.value,
            name: option.textContent.trim(),
            type: option.getAttribute('data-type')
        });
    });

    function getTypeSelectForCategorySelect(categorySelect) {
        if (!categorySelect) {
            return null;
        }

        if (categorySelect.id === 'dashboard-modal-category') {
            return document.getElementById('dashboard-modal-type');
        }

        return document.getElementById('type');
    }

    function renderCategorySelect(categorySelect, type, selectedValue) {
        if (!categorySelect) {
            return;
        }

        const selected = selectedValue !== undefined && selectedValue !== null
            ? String(selectedValue)
            : categorySelect.value;

        categorySelect.innerHTML = '';

        window.NOVIX_CATEGORY_OPTIONS.forEach((category) => {
            if (category.type !== type) {
                return;
            }

            const option = new Option(category.name, category.id);
            option.setAttribute('data-type', category.type);
            categorySelect.add(option);
        });

        const selectedOption = Array.from(categorySelect.options).find((option) => option.value === selected);
        if (selectedOption) {
            categorySelect.value = selected;
        } else if (categorySelect.options.length > 0) {
            categorySelect.value = categorySelect.options[0].value;
        }
    }

    function wireCategoryFilter(typeSelect, categorySelect) {
        if (!typeSelect || !categorySelect || categorySelect.dataset.categoryFilterReady === 'true') {
            return;
        }

        categorySelect.dataset.categoryFilterReady = 'true';
        typeSelect.addEventListener('change', () => {
            renderCategorySelect(categorySelect, typeSelect.value, categorySelect.value);
        });
        renderCategorySelect(categorySelect, typeSelect.value, categorySelect.value);
    }

    function refreshCategorySelects(activeSelect, selectedCategory) {
        document.querySelectorAll('select[name="category_id"]').forEach((categorySelect) => {
            const typeSelect = getTypeSelectForCategorySelect(categorySelect);
            if (!typeSelect) {
                return;
            }

            if (categorySelect === activeSelect) {
                typeSelect.value = selectedCategory.type;
            }

            renderCategorySelect(
                categorySelect,
                typeSelect.value,
                categorySelect === activeSelect ? selectedCategory.id : categorySelect.value
            );
        });
    }

    function initCategoryFilters() {
        wireCategoryFilter(document.getElementById('type'), document.getElementById('category'));
        wireCategoryFilter(document.getElementById('dashboard-modal-type'), document.getElementById('dashboard-modal-category'));
    }

    function initInlineCategoryCreation() {
        const modal = document.getElementById('inline-category-modal');
        const form = document.getElementById('inline-category-form');
        const nameInput = document.getElementById('inline-category-name');
        const typeInput = document.getElementById('inline-category-type');
        const errorBox = document.getElementById('inline-category-error');
        const submitButton = form ? form.querySelector('button[type="submit"]') : null;
        let activeSelect = null;

        if (!modal || !form || !nameInput || !typeInput || !submitButton) {
            return;
        }

        function setError(message) {
            if (!errorBox) {
                return;
            }

            errorBox.textContent = message || '';
            errorBox.classList.toggle('hidden', !message);
        }

        function closeModal() {
            if (typeof modal.close === 'function') {
                modal.close();
            } else {
                modal.removeAttribute('open');
            }
        }

        document.querySelectorAll('[data-inline-category-trigger="create"]').forEach((button) => {
            button.addEventListener('click', () => {
                activeSelect = document.querySelector(button.getAttribute('data-category-select') || '');
                const typeSelect = getTypeSelectForCategorySelect(activeSelect);

                form.reset();
                setError('');
                typeInput.value = typeSelect ? typeSelect.value : 'expense';

                if (typeof modal.showModal === 'function') {
                    modal.showModal();
                } else {
                    modal.setAttribute('open', 'open');
                }

                nameInput.focus();
            });
        });

        const closeButton = modal.querySelector('[data-close-inline-category]');
        if (closeButton) {
            closeButton.addEventListener('click', closeModal);
        }

        modal.addEventListener('cancel', (event) => {
            event.preventDefault();
            closeModal();
        });

        form.addEventListener('submit', (event) => {
            event.preventDefault();
            setError('');

            const params = new URLSearchParams();
            params.append('_token', window.NOVIX_CSRF_TOKEN || '');
            params.append('name', nameInput.value.trim());
            params.append('type', typeInput.value);

            submitButton.disabled = true;

            fetch('/actions/categories/create-inline.php', {
                method: 'POST',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8'
                },
                body: params.toString(),
                credentials: 'same-origin'
            }).then((response) => response.json().then((payload) => ({ response, payload })))
                .then(({ response, payload }) => {
                    if (!response.ok || !payload || payload.ok !== true) {
                        throw new Error((payload && payload.error) || 'Nao foi possivel criar a categoria.');
                    }

                    const category = appendCategoryOption({
                        id: payload.id,
                        name: payload.name,
                        type: payload.type
                    });

                    if (!category) {
                        throw new Error('Resposta invalida do servidor.');
                    }

                    refreshCategorySelects(activeSelect, category);
                    closeModal();
                })
                .catch((error) => {
                    setError(error.message || 'Nao foi possivel criar a categoria.');
                })
                .finally(() => {
                    submitButton.disabled = false;
                });
        });
    }

    initCategoryFilters();
    initInlineCategoryCreation();

    const categoriesElement = document.getElementById('chartCategories');
    const monthlyElement = document.getElementById('chartMonthly');

    if (categoriesElement && Array.isArray(categoriesData)) {
        new Chart(categoriesElement, {
            type: 'doughnut',
            data: {
                labels: categoriesData.map((item) => item.name),
                datasets: [{
                    label: 'Despesas',
                    data: categoriesData.map((item) => Number(item.total)),
                    backgroundColor: [
                        P.accent.a(0.7),
                        P.pos.a(0.8),
                        P.muted.a(0.7),
                        P.accent2.a(0.7),
                        P.gold.a(0.7),
                        P.neg.a(0.6),
                    ],
                    borderWidth: 1,
                    borderColor: P.surface.hex,
                }],
            },
            options: {
                plugins: {
                    legend: {
                        labels: {
                            color: P.muted.hex,
                        },
                    },
                },
            },
        });
    }

    if (monthlyElement && Array.isArray(monthlyData) && monthlyData.length > 0) {
        // MODERNIZAÇÃO: Adiciona projeção se disponível e período for mensal
        const hasProjection = periodType === 'monthly' && typeof projectionData !== 'undefined' && Array.isArray(projectionData) && projectionData.length > 0;
        const extendedData = hasProjection && typeof extendedHistoryData !== 'undefined' && Array.isArray(extendedHistoryData) ? extendedHistoryData : monthlyData;
        
        // Formata labels baseado no tipo de período
        const labels = monthlyData.map((item) => {
            if (periodType === 'daily') {
                // Formato: DD/MM
                const date = new Date(item.period + 'T00:00:00');
                const day = String(date.getDate()).padStart(2, '0');
                const month = String(date.getMonth() + 1).padStart(2, '0');
                return day + '/' + month;
            } else if (periodType === 'weekly') {
                // Formato: Sem XX
                const weekNum = item.week_num || item.period;
                return 'Sem ' + String(weekNum).slice(-2);
            } else if (periodType === 'yearly') {
                // Formato: Ano
                return String(item.period);
            } else {
                // Monthly - Formato: Mês/Ano
                const period = item.month || item.period;
                const [year, month] = period.split('-');
                const monthNames = ['Jan', 'Fev', 'Mar', 'Abr', 'Mai', 'Jun', 'Jul', 'Ago', 'Set', 'Out', 'Nov', 'Dez'];
                return monthNames[parseInt(month) - 1] + '/' + year.substring(2);
            }
        });

        // Adiciona labels de projeção
        const projectionLabels = hasProjection ? projectionData.map((item) => {
            const [year, month] = item.month.split('-');
            const monthNames = ['Jan', 'Fev', 'Mar', 'Abr', 'Mai', 'Jun', 'Jul', 'Ago', 'Set', 'Out', 'Nov', 'Dez'];
            return monthNames[parseInt(month) - 1] + '/' + year.substring(2) + ' (proj.)';
        }) : [];
        
        const allLabels = [...labels, ...projectionLabels];
        
        // Prepara dados históricos
        const historicalExpenses = monthlyData.map((item) => Number(item.expense) || 0);
        const historicalIncomes = monthlyData.map((item) => Number(item.income) || 0);
        
        // Prepara dados de projeção
        const projectionExpenses = hasProjection ? projectionData.map((item) => Number(item.expense) || 0) : [];
        const projectionIncomes = hasProjection ? projectionData.map((item) => Number(item.income) || 0) : [];
        
        // Combina histórico + projeção
        const allExpenses = [...historicalExpenses, ...projectionExpenses];
        const allIncomes = [...historicalIncomes, ...projectionIncomes];
        
        // Calcula ponto de divisão entre histórico e projeção
        const historicalLength = monthlyData.length;
        // Gradientes elegantes e suaves com múltiplas paradas para transições mais refinadas
        const ctx = monthlyElement.getContext('2d');
        const incomeGradient = ctx.createLinearGradient(0, 0, 0, monthlyElement.height || 300);
        incomeGradient.addColorStop(0, P.pos.a(0.3));
        incomeGradient.addColorStop(0.25, P.pos.a(0.18));
        incomeGradient.addColorStop(0.5, P.pos.a(0.1));
        incomeGradient.addColorStop(0.75, P.pos.a(0.05));
        incomeGradient.addColorStop(1, P.pos.a(0.02));
        
        const expenseGradient = ctx.createLinearGradient(0, 0, 0, monthlyElement.height || 300);
        expenseGradient.addColorStop(0, P.neg.a(0.3));
        expenseGradient.addColorStop(0.25, P.neg.a(0.18));
        expenseGradient.addColorStop(0.5, P.neg.a(0.1));
        expenseGradient.addColorStop(0.75, P.neg.a(0.05));
        expenseGradient.addColorStop(1, P.neg.a(0.02));
        
        const datasets = [
                    {
                        label: 'Receitas',
                data: allIncomes,
                borderColor: P.pos.a(0.95),
                backgroundColor: incomeGradient,
                borderWidth: 2, // Linha delicada e elegante
                tension: 0.98, // Curvas extremamente suaves, elegantes e delicadas
                        fill: true,
                pointRadius: 3, // Pontos menores e mais delicados
                pointHoverRadius: 5.5,
                pointBackgroundColor: P.pos.a(0.95),
                pointBorderColor: P.surface.hex,
                pointBorderWidth: 1.5,
                pointHoverBorderWidth: 2,
                segment: hasProjection ? {
                    borderDash: (ctx) => ctx.p1DataIndex >= historicalLength ? [6, 4] : [0, 0],
                } : undefined
                    },
                    {
                        label: 'Despesas',
                data: allExpenses,
                borderColor: P.neg.a(0.95),
                backgroundColor: expenseGradient,
                borderWidth: 2, // Linha delicada e elegante
                tension: 0.98, // Curvas extremamente suaves, elegantes e delicadas
                        fill: true,
                pointRadius: 3, // Pontos menores e mais delicados
                pointHoverRadius: 5.5,
                pointBackgroundColor: P.neg.a(0.95),
                pointBorderColor: P.surface.hex,
                pointBorderWidth: 1.5,
                pointHoverBorderWidth: 2,
                segment: hasProjection ? {
                    borderDash: (ctx) => ctx.p1DataIndex >= historicalLength ? [6, 4] : [0, 0],
                } : undefined
                    },
                    {
                        label: 'Saldo Acumulado',
                        data: (() => {
                            let accumulated = typeof initialBalance !== 'undefined' ? initialBalance : 0;
                    const historical = monthlyData.map((item) => {
                                accumulated += (Number(item.income) || 0) - (Number(item.expense) || 0);
                                return accumulated;
                            });
                    const projected = hasProjection ? projectionData.map((item) => {
                        accumulated += (Number(item.income) || 0) - (Number(item.expense) || 0);
                        return accumulated;
                    }) : [];
                    return [...historical, ...projected];
                        })(),
                borderColor: P.accent.a(0.9),
                backgroundColor: P.accent.a(0.08),
                borderWidth: 2, // Linha delicada e elegante
                tension: 0.98, // Curvas extremamente suaves, elegantes e delicadas
                        fill: false,
                borderDash: [6, 3.5],
                pointRadius: 3,
                pointHoverRadius: 5.5,
                pointBackgroundColor: P.accent.a(0.9),
                        pointBorderColor: P.surface.hex,
                pointBorderWidth: 1.5,
                pointHoverBorderWidth: 2,
                segment: hasProjection ? {
                    borderDash: (ctx) => ctx.p1DataIndex >= historicalLength ? [10, 5] : [7, 4],
                } : undefined
                    },
        ];
        
        // Adiciona linha divisória se houver projeção
        if (hasProjection) {
            datasets.push({
                label: 'Divisória',
                data: Array(historicalLength).fill(null).concat(Array(projectionData.length).fill(null)),
                borderColor: P.muted.a(0.3),
                borderWidth: 2,
                borderDash: [2, 2],
                pointRadius: 0,
                fill: false,
                spanGaps: true
            });
        }

        new Chart(monthlyElement, {
            type: 'line',
            data: {
                labels: allLabels,
                datasets: datasets,
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                aspectRatio: 2,
                animation: {
                    duration: 1200,
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
                            font: { size: 11 }
                        },
                        grid: {
                            color: P.muted.a(0.12),
                            lineWidth: 1
                        }
                    },
                    y: {
                        beginAtZero: true,
                        ticks: {
                            color: P.muted.hex,
                            font: { size: 11 },
                            callback: function(value) {
                                return 'R$ ' + value.toLocaleString('pt-BR', {minimumFractionDigits: 0, maximumFractionDigits: 0});
                            }
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
                            padding: 12,
                            usePointStyle: true,
                            font: { size: 12 },
                            boxWidth: 8,
                            boxHeight: 8
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
                            label: function(context) {
                                let label = context.dataset.label || '';
                                if (label) {
                                    label += ': ';
                                }
                                const value = context.parsed.y;
                                label += 'R$ ' + value.toLocaleString('pt-BR', {minimumFractionDigits: 2, maximumFractionDigits: 2});
                                return label;
                            }
                        }
                    }
                },
            },
        });
    }

    // Gráfico de Despesas por Conta
    const expensesByAccountElement = document.getElementById('chartExpensesByAccount');
    if (expensesByAccountElement && typeof expensesByAccountData !== 'undefined' && Array.isArray(expensesByAccountData) && expensesByAccountData.length > 0) {
        new Chart(expensesByAccountElement, {
            type: 'doughnut',
            data: {
                labels: expensesByAccountData.map(item => (item.icon || '🏦') + ' ' + item.name),
                datasets: [{
                    label: 'Despesas',
                    data: expensesByAccountData.map(item => item.amount),
                    backgroundColor: [
                        P.neg.a(0.85),
                        P.neg.a(0.72),
                        P.neg.a(0.6),
                        P.neg.a(0.48),
                        P.neg.a(0.38),
                        P.neg.a(0.9),
                        P.neg.a(0.62),
                        P.neg.a(0.44),
                    ],
                    borderWidth: 2,
                    borderColor: dashboardChartTheme.pieBorderColor,
                }],
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: {
                            color: dashboardChartTheme.pieLegendColor,
                            padding: 12,
                            font: { size: 11 }
                        },
                    },
                    tooltip: {
                        backgroundColor: P.ink.a(0.95),
                        titleColor: P.surface.hex,
                        bodyColor: P.surface.a(0.82),
                        callbacks: {
                            label: function(context) {
                                const value = context.parsed || 0;
                                return 'R$ ' + value.toLocaleString('pt-BR', {minimumFractionDigits: 2, maximumFractionDigits: 2});
                            }
                        }
                    }
                },
            },
        });
    }

    // Gráfico de Receitas por Conta
    const incomesByAccountElement = document.getElementById('chartIncomesByAccount');
    if (incomesByAccountElement && typeof incomesByAccountData !== 'undefined' && Array.isArray(incomesByAccountData) && incomesByAccountData.length > 0) {
        new Chart(incomesByAccountElement, {
            type: 'doughnut',
            data: {
                labels: incomesByAccountData.map(item => (item.icon || '🏦') + ' ' + item.name),
                datasets: [{
                    label: 'Receitas',
                    data: incomesByAccountData.map(item => item.amount),
                    backgroundColor: [
                        P.pos.a(0.8),
                        P.pos.a(0.72),
                        P.pos.a(0.6),
                        P.pos.a(0.48),
                        P.pos.a(0.38),
                        P.pos.a(0.9),
                        P.pos.a(0.62),
                        P.pos.a(0.44),
                    ],
                    borderWidth: 2,
                    borderColor: dashboardChartTheme.pieBorderColor,
                }],
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: {
                            color: dashboardChartTheme.pieLegendColor,
                            padding: 12,
                            font: { size: 11 }
                        },
                    },
                    tooltip: {
                        backgroundColor: P.ink.a(0.95),
                        titleColor: P.surface.hex,
                        bodyColor: P.surface.a(0.82),
                        callbacks: {
                            label: function(context) {
                                const value = context.parsed || 0;
                                return 'R$ ' + value.toLocaleString('pt-BR', {minimumFractionDigits: 2, maximumFractionDigits: 2});
                            }
                        }
                    }
                },
            },
        });
    }

    // MODERNIZAÇÃO: Gráfico de Previsão de Gastos - Agora gerenciado por forecast-chart.js
    // O código antigo foi movido para forecast-chart.js que gerencia visualização por período (semanal/mensal/anual)
    // Este bloco é mantido apenas como fallback se forecast-chart.js não estiver disponível
    const forecastElement = document.getElementById('chartForecast');
    if (forecastElement && typeof initForecastChart === 'undefined' && typeof extendedHistoryData !== 'undefined' && Array.isArray(extendedHistoryData) && extendedHistoryData.length > 0 && typeof projectionData !== 'undefined' && Array.isArray(projectionData) && projectionData.length > 0) {
        const monthNames = ['Jan', 'Fev', 'Mar', 'Abr', 'Mai', 'Jun', 'Jul', 'Ago', 'Set', 'Out', 'Nov', 'Dez'];
        // Usa até 6 meses de histórico, ou todos se tiver menos
        const last6 = extendedHistoryData.slice(-6);
        
        // Verifica se há dados suficientes
        if (last6.length === 0 || projectionData.length === 0) {
            return;
        }
        
        const historicalLabels = last6.map(item => {
            const period = item.month || item.period;
            const [year, month] = period.split('-');
            return `${monthNames[parseInt(month) - 1]}/${year.substring(2)}`;
        });
        const projectedLabels = projectionData.map(item => {
            const [year, month] = item.month.split('-');
            return `${monthNames[parseInt(month) - 1]}/${year.substring(2)} (proj.)`;
        });
        
        // MODERNIZAÇÃO: Adiciona labels de previsões semanais se disponíveis
        let weeklyLabels = [];
        let hasWeeklyProjections = typeof weeklyProjectionData !== 'undefined' && Array.isArray(weeklyProjectionData) && weeklyProjectionData.length > 0;
        
        if (hasWeeklyProjections) {
            weeklyLabels = weeklyProjectionData.map(item => {
                return item.week_label || `Sem ${item.week}`;
            });
        }
        
        // Combina todos os labels: histórico + projeções mensais + previsões semanais
        const labels = [...historicalLabels, ...projectedLabels, ...weeklyLabels];
        
        const historicalExpenses = last6.map(item => Number(item.expense) || 0);
        const projectedExpenses = projectionData.map(item => Number(item.expense) || 0);
        
        // MODERNIZAÇÃO: Previsões semanais
        const weeklyProjectedExpenses = hasWeeklyProjections ? weeklyProjectionData.map(item => Number(item.expense) || 0) : [];
        
        const historicalLength = historicalExpenses.length;
        const projectionLength = projectedExpenses.length;
        const weeklyLength = weeklyProjectedExpenses.length;
        
        // Último valor histórico para conectar com a projeção
        const lastHistoricalValue = historicalExpenses[historicalLength - 1];
        
        // Prepara dados históricos (completo, sem nulls)
        const historicalData = historicalExpenses.map(v => v);
        
        // Faixa de confiança: ±20% sobre a projeção mensal + semanal
        const confidenceUpper = [
            ...Array(historicalLength - 1).fill(null),
            lastHistoricalValue * 1.2,
            ...projectedExpenses.map(v => v * 1.2),
            ...weeklyProjectedExpenses.map(v => v * 1.2),
        ];
        const confidenceLower = [
            ...Array(historicalLength - 1).fill(null),
            lastHistoricalValue * 0.8,
            ...projectedExpenses.map(v => v * 0.8),
            ...weeklyProjectedExpenses.map(v => v * 0.8),
        ];
        
        // Para o gráfico, precisamos arrays do mesmo tamanho que labels
        // Histórico: valores históricos completos + nulls para projeções (linha termina no último histórico)
        const historicalForChart = [...historicalData, ...Array(projectionLength + weeklyLength).fill(null)];
        
        // Projeção mensal: nulls para histórico anterior + último histórico + valores projetados mensais + nulls para semanais
        const monthlyProjectionForChart = [
            ...Array(historicalLength - 1).fill(null),
            lastHistoricalValue, // Último ponto histórico - conecta com a linha
            ...projectedExpenses, // Valores projetados mensais
            ...Array(weeklyLength).fill(null), // Nulls para semanais (serão preenchidos no dataset semanal)
        ];
        
        // Projeção semanal: nulls para histórico e mensal + valores semanais
        const weeklyProjectionForChart = [
            ...Array(historicalLength - 1).fill(null),
            ...Array(projectionLength).fill(null), // Nulls para projeções mensais
            ...weeklyProjectedExpenses, // Valores projetados semanais
        ];
        
        // Garante que todos os arrays têm exatamente o mesmo tamanho que labels
        const targetLength = labels.length;
        
        // Ajusta tamanhos
        while (historicalForChart.length < targetLength) {
            historicalForChart.push(null);
        }
        while (monthlyProjectionForChart.length < targetLength) {
            monthlyProjectionForChart.push(null);
        }
        while (weeklyProjectionForChart.length < targetLength) {
            weeklyProjectionForChart.push(null);
        }
        while (confidenceUpper.length < targetLength) {
            confidenceUpper.push(null);
        }
        while (confidenceLower.length < targetLength) {
            confidenceLower.push(null);
        }
        
        // Trunca se necessário (não deveria acontecer, mas é uma segurança)
        historicalForChart.length = targetLength;
        monthlyProjectionForChart.length = targetLength;
        weeklyProjectionForChart.length = targetLength;
        confidenceUpper.length = targetLength;
        confidenceLower.length = targetLength;
        
        // Gradientes bonitos para área de confiança e histórico
        const ctx = forecastElement.getContext('2d');
        const confidenceGradient = ctx.createLinearGradient(0, 0, 0, forecastElement.height || 300);
        confidenceGradient.addColorStop(0, P.accent.a(0.35));
        confidenceGradient.addColorStop(0.5, P.accent.a(0.15));
        confidenceGradient.addColorStop(1, P.accent.a(0.05));
        
        const historicalGradient = ctx.createLinearGradient(0, 0, 0, forecastElement.height || 300);
        historicalGradient.addColorStop(0, P.neg.a(0.3));
        historicalGradient.addColorStop(0.5, P.neg.a(0.15));
        historicalGradient.addColorStop(1, P.neg.a(0.05));

        new Chart(forecastElement, {
            type: 'line',
            data: {
                labels,
                datasets: [
                    {
                        label: 'Limite superior (±20%)',
                        data: confidenceUpper,
                        borderColor: P.accent.a(0.6),
                        backgroundColor: P.accent.a(0),
                        fill: false,
                        pointRadius: 0,
                        pointHoverRadius: 0,
                        borderWidth: 1,
                        borderDash: [4, 4],
                        tension: 0.5,
                        order: 4,
                        spanGaps: false,
                    },
                    {
                        label: 'Limite inferior',
                        data: confidenceLower,
                        borderColor: P.accent.a(0.6),
                        backgroundColor: confidenceGradient,
                        fill: '-1', // Preenche até o dataset anterior (limite superior)
                        pointRadius: 0,
                        pointHoverRadius: 0,
                        borderWidth: 1.5,
                        borderDash: [5, 5],
                        tension: 0.6,
                        order: 4,
                        spanGaps: false,
                    },
                    {
                        label: 'Histórico',
                        data: historicalForChart,
                        borderColor: P.neg.a(0.9),
                        backgroundColor: historicalGradient,
                        tension: 0.8, // Curvas extremamente suaves e delicadas
                        borderWidth: 2.5, // Linha mais delicada
                        pointRadius: 4,
                        pointHoverRadius: 7,
                        pointBackgroundColor: P.neg.a(0.9),
                        pointBorderColor: P.surface.hex,
                        pointBorderWidth: 2,
                        pointHoverBorderWidth: 2.5,
                        fill: true,
                        order: 3,
                        spanGaps: false,
                        shadowOffsetX: 0,
                        shadowOffsetY: 2,
                        shadowBlur: 8,
                        shadowColor: P.neg.a(0.25),
                    },
                    {
                        label: 'Projeção Mensal',
                        data: monthlyProjectionForChart,
                        borderColor: P.accent.a(0.85),
                        backgroundColor: P.accent.a(0),
                        tension: 0.8, // Curvas extremamente suaves e delicadas
                        borderWidth: 2.5, // Linha mais delicada
                        borderDash: [8, 4],
                        pointRadius: 4,
                        pointHoverRadius: 7,
                        pointBackgroundColor: P.accent.a(0.85),
                        pointBorderColor: P.surface.hex,
                        pointBorderWidth: 2,
                        pointHoverBorderWidth: 2.5,
                        fill: false,
                        spanGaps: true,
                        order: 2,
                        shadowOffsetX: 0,
                        shadowOffsetY: 2,
                        shadowBlur: 8,
                        shadowColor: P.accent.a(0.25),
                    },
                    ...(hasWeeklyProjections && weeklyLength > 0 ? [{
                        label: 'Previsão Semanal',
                        data: weeklyProjectionForChart,
                        borderColor: P.gold.a(0.85),
                        backgroundColor: P.gold.a(0.08),
                        tension: 0.8, // Curvas extremamente suaves e delicadas
                        borderWidth: 2, // Linha mais delicada
                        borderDash: [5, 3],
                        pointRadius: 3.5,
                        pointHoverRadius: 6,
                        pointBackgroundColor: P.gold.a(0.85),
                        pointBorderColor: P.surface.hex,
                        pointBorderWidth: 1.5,
                        pointHoverBorderWidth: 2,
                        fill: false,
                        spanGaps: true,
                        order: 1,
                        shadowOffsetX: 0,
                        shadowOffsetY: 2,
                        shadowBlur: 6,
                        shadowColor: P.gold.a(0.25),
                    }] : []),
                ],
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                interaction: {
                    mode: 'index',
                    intersect: false,
                },
                scales: {
                    x: {
                        ticks: { color: P.muted.hex },
                        grid: { color: P.muted.a(0.14) },
                    },
                    y: {
                        beginAtZero: true,
                        ticks: {
                            color: P.muted.hex,
                            callback: (value) => 'R$ ' + value.toLocaleString('pt-BR', { minimumFractionDigits: 0, maximumFractionDigits: 0 }),
                        },
                        grid: { color: P.muted.a(0.14) },
                    },
                },
                plugins: {
                    legend: {
                        display: true,
                        position: 'top',
                        labels: {
                            color: P.muted.hex,
                            usePointStyle: true,
                            padding: 15,
                            font: { size: 12 },
                            filter: (item) => {
                                // Oculta "Limite superior" e "Limite inferior" da legenda (mantém apenas para visualização)
                                return item.text !== 'Limite superior (±20%)' && item.text !== 'Limite inferior';
                            },
                        },
                    },
                    tooltip: {
                        backgroundColor: P.ink.a(0.95),
                        titleColor: P.surface.hex,
                        bodyColor: P.surface.a(0.82),
                        borderColor: P.muted.a(0.1),
                        borderWidth: 1,
                        padding: 12,
                        callbacks: {
                            label: (context) => {
                                let label = context.dataset.label || '';
                                const value = context.parsed.y;
                                if (value !== null && value !== undefined) {
                                    label += ': R$ ' + value.toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
                                }
                                return label;
                            },
                        },
                    },
                },
            },
        });
    }

    // Modais de edição e exclusão de transações
    const editModal = document.getElementById('modal-edit-dashboard');
    const deleteModal = document.getElementById('modal-delete-dashboard');

    // Modal de edição
    if (editModal && Array.isArray(dashboardTransactionsData)) {
        const closeEditModal = () => {
            editModal.close();
        };

        document.querySelectorAll('[data-edit]').forEach((button) => {
            button.addEventListener('click', () => {
                const id = Number(button.getAttribute('data-edit'));
                const transaction = dashboardTransactionsData.find((item) => Number(item.id) === id);

                if (!transaction) {
                    return;
                }

                editModal.querySelector('input[name="id"]').value = transaction.id;
                editModal.querySelector('select[name="account_id"]').value = transaction.account_id || '';
                editModal.querySelector('select[name="type"]').value = transaction.type;
                editModal.querySelector('input[name="amount"]').value = Number(transaction.amount);
                editModal.querySelector('select[name="currency"]').value = transaction.currency || 'BRL';
                editModal.querySelector('input[name="description"]').value = transaction.description ?? '';
                editModal.querySelector('input[name="transaction_date"]').value = transaction.transaction_date;
                editModal.querySelector('input[name="is_effective"]').checked = transaction.is_effective ?? true;
                editModal.querySelector('input[name="is_recurring"]').checked = transaction.is_recurring ?? false;

                const recurrenceTypeSelect = editModal.querySelector('select[name="recurrence_type"]');
                const recurrenceDaySelect = editModal.querySelector('select[name="recurrence_day"]');
                const recurrenceEndInput = editModal.querySelector('input[name="recurrence_end_date"]');
                const recurrenceLimitModeSelect = editModal.querySelector('select[name="recurrence_limit_mode"]');
                const recurrenceTotalInput = editModal.querySelector('input[name="recurrence_total_occurrences"]');
                if (recurrenceTypeSelect) {
                    recurrenceTypeSelect.value = transaction.recurrence_type || 'monthly';
                }
                if (recurrenceDaySelect && transaction.recurrence_day !== null && transaction.recurrence_day !== undefined) {
                    recurrenceDaySelect.value = String(transaction.recurrence_day);
                } else if (recurrenceDaySelect) {
                    recurrenceDaySelect.value = '';
                }
                if (recurrenceEndInput) {
                    recurrenceEndInput.value = transaction.recurrence_end_date || '';
                }
                if (recurrenceTotalInput) {
                    recurrenceTotalInput.value = transaction.recurrence_total_occurrences || '';
                }
                if (recurrenceLimitModeSelect) {
                    if (transaction.recurrence_total_occurrences) {
                        recurrenceLimitModeSelect.value = 'count';
                    } else if (transaction.recurrence_end_date) {
                        recurrenceLimitModeSelect.value = 'date';
                    } else {
                        recurrenceLimitModeSelect.value = 'never';
                    }
                }
                toggleRecurrenceFieldsEditDashboard();
                
                // Atualiza campo de data de efetivação
                const effectiveDateInput = editModal.querySelector('input[name="effective_date"]');
                if (effectiveDateInput) {
                    // Carrega a data existente se houver, ou deixa vazio
                    if (transaction.effective_date) {
                        effectiveDateInput.value = transaction.effective_date;
                    } else {
                        effectiveDateInput.value = '';
                    }
                    
                    // Define a data mínima como a data da transação
                    const transactionDate = new Date(transaction.transaction_date);
                    if (!isNaN(transactionDate.getTime())) {
                        effectiveDateInput.min = transactionDate.toISOString().split('T')[0];
                    }
                }

                // Seleciona a categoria correta
                const categorySelect = editModal.querySelector('select[name="category_id"]');
                categorySelect.value = transaction.category_id;

                // Filtra categorias baseado no tipo
                const typeSelect = editModal.querySelector('select[name="type"]');
                renderCategorySelect(categorySelect, typeSelect.value, transaction.category_id);

                // Atualiza visibilidade do campo de data de efetivação
                toggleEffectiveDateEditDashboard();

                editModal.showModal();
            });
        });

        const closeEditBtn = editModal.querySelector('[data-close-edit-modal-dashboard]');
        if (closeEditBtn) {
            closeEditBtn.addEventListener('click', closeEditModal);
        }
        editModal.addEventListener('cancel', (event) => {
            event.preventDefault();
            closeEditModal();
        });
    }

    // Modal de exclusão
    if (deleteModal) {
        const closeDeleteModal = () => {
            deleteModal.close();
        };

        document.querySelectorAll('[data-delete]').forEach((button) => {
            button.addEventListener('click', () => {
                const id = button.getAttribute('data-delete');
                deleteModal.querySelector('#dashboard-delete-id').value = id;
                deleteModal.showModal();
            });
        });

        const closeDeleteBtn = deleteModal.querySelector('[data-close-delete-modal-dashboard]');
        if (closeDeleteBtn) {
            closeDeleteBtn.addEventListener('click', closeDeleteModal);
        }
        deleteModal.addEventListener('cancel', (event) => {
            event.preventDefault();
            closeDeleteModal();
        });
    }
    
    window.toggleRecurrenceFieldsEditDashboard = function() {
        const isRecurring = document.getElementById('dashboard-modal-is-recurring');
        const container = document.getElementById('dashboard-modal-recurrence-fields');
        if (!isRecurring || !container) {
            return;
        }
        if (isRecurring.checked) {
            container.classList.remove('hidden');
            toggleRecurrenceDayEditDashboard();
            toggleRecurrenceLimitFieldsEditDashboard();
        } else {
            container.classList.add('hidden');
            const typeSelect = document.getElementById('dashboard-modal-recurrence-type');
            const daySelect = document.getElementById('dashboard-modal-recurrence-day');
            const limitSelect = document.getElementById('dashboard-modal-recurrence-limit-mode');
            const endInput = document.getElementById('dashboard-modal-recurrence-end-date');
            const totalInput = document.getElementById('dashboard-modal-recurrence-total-occurrences');
            if (typeSelect) typeSelect.value = 'none';
            if (daySelect) daySelect.value = '';
            if (limitSelect) limitSelect.value = 'never';
            if (endInput) endInput.value = '';
            if (totalInput) totalInput.value = '';
            toggleRecurrenceLimitFieldsEditDashboard();
        }
    };

    window.toggleRecurrenceDayEditDashboard = function() {
        const recurrenceType = document.getElementById('dashboard-modal-recurrence-type');
        const dayContainer = document.getElementById('dashboard-modal-recurrence-day-container');
        if (!recurrenceType || !dayContainer) {
            return;
        }
        if (recurrenceType.value === 'weekly') {
            dayContainer.classList.remove('hidden');
        } else {
            dayContainer.classList.add('hidden');
            const daySelect = document.getElementById('dashboard-modal-recurrence-day');
            if (daySelect) daySelect.value = '';
        }
    };

    // Função para mostrar/ocultar campo de data de efetivação no dashboard
    window.toggleRecurrenceLimitFieldsEditDashboard = function() {
        const modeSelect = document.getElementById('dashboard-modal-recurrence-limit-mode');
        const dateContainer = document.getElementById('dashboard-modal-recurrence-end-date-container');
        const countContainer = document.getElementById('dashboard-modal-recurrence-total-container');
        const endInput = document.getElementById('dashboard-modal-recurrence-end-date');
        const totalInput = document.getElementById('dashboard-modal-recurrence-total-occurrences');
        if (!modeSelect || !dateContainer || !countContainer) {
            return;
        }

        dateContainer.classList.toggle('hidden', modeSelect.value !== 'date');
        countContainer.classList.toggle('hidden', modeSelect.value !== 'count');

        if (modeSelect.value !== 'date' && endInput) {
            endInput.value = '';
        }
        if (modeSelect.value !== 'count' && totalInput) {
            totalInput.value = '';
        }
    };

    window.toggleEffectiveDateEditDashboard = function() {
        const isEffective = document.getElementById('dashboard-modal-is-effective');
        const container = document.getElementById('dashboard-modal-effective-date-container');
        const effectiveDateInput = document.getElementById('dashboard-modal-effective-date');
        const transactionDateInput = document.getElementById('dashboard-modal-date');
        
        if (!container || !isEffective) {
            return;
        }
        
        if (!isEffective.checked) {
            // Mostra o campo quando não está efetivada
            container.classList.remove('hidden');
            container.style.display = 'block';
            
            if (effectiveDateInput) {
                // Define a data mínima como a data da transação (ou hoje se não tiver)
                let minDate = new Date().toISOString().split('T')[0];
                if (transactionDateInput && transactionDateInput.value) {
                    const transactionDate = new Date(transactionDateInput.value);
                    if (!isNaN(transactionDate.getTime())) {
                        minDate = transactionDate.toISOString().split('T')[0];
                    }
                }
                effectiveDateInput.min = minDate;
            }
        } else {
            // Oculta o campo quando está efetivada
            container.classList.add('hidden');
            container.style.display = 'none';
            
            if (effectiveDateInput) {
                // Limpa o campo quando oculta (será salvo como null)
                effectiveDateInput.value = '';
            }
        }
    };
});

