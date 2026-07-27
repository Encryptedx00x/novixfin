// MODERNIZAÇÃO: Sistema Kanban para categorias
document.addEventListener('DOMContentLoaded', () => {
    const kanbanContainer = document.getElementById('kanbanContainer');
    const toggleKanbanBtn = document.getElementById('toggleKanbanView');
    const kanbanViewText = document.getElementById('kanbanViewText');
    
    if (!kanbanContainer || !toggleKanbanBtn) return;
    
    // Carrega classificação salva do localStorage
    const loadKanbanClassification = () => {
        const saved = localStorage.getItem('kanbanClassification');
        return saved ? JSON.parse(saved) : {};
    };
    
    // Salva classificação no localStorage
    const saveKanbanClassification = (classification) => {
        localStorage.setItem('kanbanClassification', JSON.stringify(classification));
    };
    
    // Renderiza cards de categoria no Kanban
    const renderKanbanCards = () => {
        if (typeof categoryVariationsData === 'undefined' || !Array.isArray(categoryVariationsData)) return;
        
        const classification = loadKanbanClassification();
        const columns = {
            recurring: document.querySelector('[data-column="recurring"]'),
            essential: document.querySelector('[data-column="essential"]'),
            optional: document.querySelector('[data-column="optional"]'),
            leisure: document.querySelector('[data-column="leisure"]'),
        };
        
        // Limpa colunas
        Object.values(columns).forEach(col => {
            if (col) col.innerHTML = '';
        });
        
        // Distribui categorias
        categoryVariationsData.forEach(cat => {
            const columnName = classification[cat.id] || getDefaultColumn(cat);
            const column = columns[columnName];
            
            if (!column) return;
            
            // Variação em 3 estados (tokens do tema, sem cor fixa):
            // gasto subiu -> --neg; gasto caiu -> --pos; sem mudança -> --muted.
            const diff = Number(cat.difference) || 0;
            let varColor;
            let arrow;
            if (Math.abs(diff) < 0.005) {
                varColor = 'var(--muted)';
                arrow = '→';
            } else if (diff > 0) {
                varColor = 'var(--neg)';
                arrow = '↑';
            } else {
                varColor = 'var(--pos)';
                arrow = '↓';
            }

            const fmt = (v) => Number(v).toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

            const card = document.createElement('div');
            card.className = 'dashboard-kanban-card';
            card.draggable = true;
            card.dataset.categoryId = cat.id;
            card.dataset.column = columnName;
            card.ondragstart = drag;
            card.ondragend = dragEnd;

            card.innerHTML = `
                <div style="display:flex; align-items:flex-start; justify-content:space-between; gap:8px; margin-bottom:8px;">
                    <span style="font-size:13px; font-weight:700; color:var(--ink); min-width:0; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;">${cat.name}</span>
                    <span style="font-family:var(--f-m); font-size:11.5px; font-weight:700; color:${varColor}; white-space:nowrap; flex:none;">${arrow} ${Math.abs(Number(cat.percentage) || 0).toFixed(1)}%</span>
                </div>
                <div style="display:flex; align-items:baseline; justify-content:space-between; gap:8px;">
                    <span style="font-size:11px; color:var(--muted);">Atual</span>
                    <span data-balance style="font-family:var(--f-num); font-size:13px; font-weight:700; color:var(--ink);">R$ ${fmt(cat.current)}</span>
                </div>
                <div style="display:flex; align-items:baseline; justify-content:space-between; gap:8px; margin-top:3px;">
                    <span style="font-size:11px; color:var(--muted);">Anterior</span>
                    <span data-balance style="font-family:var(--f-num); font-size:12px; color:var(--muted);">R$ ${fmt(cat.previous)}</span>
                </div>
            `;

            column.appendChild(card);
        });
    };
    
    // Determina coluna padrão baseado em características da categoria
    const getDefaultColumn = (cat) => {
        if (cat.has_recurring) {
            return 'recurring';
        }
        // Fallback: nome da categoria indica despesa fixa/recorrente
        if (cat.name.toLowerCase().includes('fixo') || cat.name.toLowerCase().includes('recorrente')) {
            return 'recurring';
        }
        // Se tem gasto alto e essencial (ex: moradia, alimentação)
        if (cat.current > 500 && ['Moradia', 'Alimentação', 'Saúde', 'Transporte'].includes(cat.name)) {
            return 'essential';
        }
        // Se tem gasto baixo, provavelmente lazer
        if (cat.current < 200) {
            return 'leisure';
        }
        // Padrão: opcional
        return 'optional';
    };
    
    // Estado inicial (ativo por padrão)
    const savedState = localStorage.getItem('kanbanVisible');
    const shouldShowKanban = savedState === null ? true : savedState === 'true';
    if (shouldShowKanban) {
        kanbanContainer.classList.remove('hidden');
        kanbanViewText.textContent = 'Ocultar Kanban';
        renderKanbanCards();
    } else {
        kanbanContainer.classList.add('hidden');
        kanbanViewText.textContent = 'Ver Kanban';
    }

    // Toggle visualização Kanban
    toggleKanbanBtn.addEventListener('click', () => {
        const isVisible = !kanbanContainer.classList.contains('hidden');
        kanbanContainer.classList.toggle('hidden');
        const nowVisible = !isVisible;
        kanbanViewText.textContent = nowVisible ? 'Ocultar Kanban' : 'Ver Kanban';
        localStorage.setItem('kanbanVisible', nowVisible.toString());
        
        if (nowVisible) {
            renderKanbanCards();
        }
    });
    
    // Drag and Drop
    function allowDrop(ev) {
        ev.preventDefault();
    }
    
    function drag(ev) {
        ev.dataTransfer.setData('text/plain', ev.target.dataset.categoryId);
        ev.target.style.opacity = '0.5';
    }
    
    function dragEnd(ev) {
        ev.target.style.opacity = '1';
    }
    
    window.drop = function(ev) {
        ev.preventDefault();
        const categoryId = ev.dataTransfer.getData('text/plain');
        const targetColumn = ev.currentTarget.dataset.column;
        const card = document.querySelector(`[data-category-id="${categoryId}"]`);
        
        if (!card || !targetColumn) return;
        
        // Move card para nova coluna
        ev.currentTarget.appendChild(card);
        card.dataset.column = targetColumn;
        
        // Salva classificação
        const classification = loadKanbanClassification();
        classification[categoryId] = targetColumn;
        saveKanbanClassification(classification);
    };
    
    window.allowDrop = allowDrop;
});

