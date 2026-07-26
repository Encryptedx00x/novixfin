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
            
            const isPositive = cat.is_positive;
            const colorClass = isPositive ? 'text-emerald-300' : 'text-rose-300';
            const bgClass = isPositive ? 'bg-emerald-500/10 border-emerald-500/30' : 'bg-rose-500/10 border-rose-500/30';
            const icon = isPositive ? '↓' : '↑';
            
            const card = document.createElement('div');
            card.className = 'dashboard-kanban-card p-3 rounded-lg bg-slate-800/50 border border-white/10 hover:border-violet-500/50 hover:shadow-lg transition-all duration-200 cursor-move animate-scale-in hover-lift';
            card.draggable = true;
            card.dataset.categoryId = cat.id;
            card.dataset.column = columnName;
            card.ondragstart = drag;
            card.ondragend = dragEnd;
            
            card.innerHTML = `
                <div class="flex items-start justify-between mb-2">
                    <p class="text-sm font-medium text-white">${cat.name}</p>
                    <span class="text-xs ${colorClass}">${icon} ${Math.abs(cat.percentage).toFixed(1)}%</span>
                </div>
                <p class="text-xs text-slate-400 mb-1">Atual: R$ ${cat.current.toLocaleString('pt-BR', {minimumFractionDigits: 2})}</p>
                <p class="text-xs text-slate-500">Anterior: R$ ${cat.previous.toLocaleString('pt-BR', {minimumFractionDigits: 2})}</p>
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

