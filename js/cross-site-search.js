/**
 * Sistema de Búsqueda Cross-Site
 * Busca el mismo producto en diferentes tiendas
 */

class CrossSiteSearch {
    constructor() {
        this.apiUrl = 'api/cross-site-search.php';
        this.currentGroupId = null;
    }

    /**
     * Iniciar búsqueda cross-site para un producto
     */
    async searchProduct(urlId) {
        try {
            const response = await fetch(`${this.apiUrl}?action=search`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({ url_id: urlId })
            });

            const data = await response.json();

            if (!data.success) {
                throw new Error(data.error || 'Error en la búsqueda');
            }

            this.currentGroupId = data.group_id;
            return data;

        } catch (error) {
            console.error('Error en búsqueda cross-site:', error);
            throw error;
        }
    }

    /**
     * Obtener comparación de precios de un grupo
     */
    async getComparison(groupId) {
        try {
            const response = await fetch(`${this.apiUrl}?action=comparison&group_id=${groupId}`);
            const data = await response.json();

            if (!data.success) {
                throw new Error(data.error || 'Error obteniendo comparación');
            }

            return data;

        } catch (error) {
            console.error('Error obteniendo comparación:', error);
            throw error;
        }
    }

    /**
     * Añadir producto encontrado al grupo
     */
    async addToGroup(groupId, url, targetPrice = 0) {
        try {
            const response = await fetch(`${this.apiUrl}?action=add-to-group`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    group_id: groupId,
                    url: url,
                    target_price: targetPrice
                })
            });

            const data = await response.json();

            if (!data.success) {
                throw new Error(data.error || 'Error añadiendo producto');
            }

            return data;

        } catch (error) {
            console.error('Error añadiendo producto al grupo:', error);
            throw error;
        }
    }

    /**
     * Mostrar modal de búsqueda cross-site
     */
    showSearchModal(urlId, productName) {
        const modalHtml = `
            <div id="crossSiteModal" class="modal" style="display: block;">
                <div class="modal-content" style="max-width: 900px;">
                    <span class="close" onclick="document.getElementById('crossSiteModal').remove()">&times;</span>
                    <h2>🔍 Buscar en otras tiendas</h2>
                    <p><strong>Producto:</strong> ${productName}</p>

                    <div id="searchResults" style="margin-top: 20px;">
                        <div class="loading">
                            <p>🔄 Analizando producto y buscando en tiendas relacionadas...</p>
                        </div>
                    </div>
                </div>
            </div>
        `;

        // Eliminar modal anterior si existe
        const existingModal = document.getElementById('crossSiteModal');
        if (existingModal) {
            existingModal.remove();
        }

        // Añadir modal al DOM
        document.body.insertAdjacentHTML('beforeend', modalHtml);

        // Iniciar búsqueda
        this.performSearch(urlId);
    }

    /**
     * Realizar búsqueda y mostrar resultados
     */
    async performSearch(urlId) {
        try {
            const data = await this.searchProduct(urlId);

            const resultsHtml = `
                <div class="search-results">
                    <div class="product-info" style="background: #f0f8ff; padding: 15px; border-radius: 5px; margin-bottom: 20px;">
                        <h3>📦 Información del Producto</h3>
                        <p><strong>Marca:</strong> ${data.product_info.brand || 'No detectada'}</p>
                        <p><strong>Modelo:</strong> ${data.product_info.model || 'No detectado'}</p>
                        <p><strong>Categoría:</strong> ${data.product_info.category}</p>
                        <p><strong>Términos de búsqueda:</strong> ${data.product_info.search_terms.join(', ')}</p>
                    </div>

                    <div class="store-links">
                        <h3>🏪 Tiendas donde buscar (${data.search_urls.length})</h3>
                        <p style="color: #666; margin-bottom: 15px;">
                            Haz clic en las tiendas para buscar el producto. Cuando encuentres una URL similar, cópiala y añádela abajo.
                        </p>

                        <div class="store-grid" style="display: grid; grid-template-columns: repeat(auto-fill, minmax(250px, 1fr)); gap: 10px; margin-bottom: 20px;">
                            ${data.search_urls.map(store => `
                                <a href="${store.search_url}" target="_blank" class="store-card" style="
                                    display: block;
                                    padding: 15px;
                                    background: white;
                                    border: 2px solid #ddd;
                                    border-radius: 8px;
                                    text-decoration: none;
                                    color: #333;
                                    transition: all 0.3s;
                                " onmouseover="this.style.borderColor='#007bff'; this.style.transform='translateY(-2px)'"
                                   onmouseout="this.style.borderColor='#ddd'; this.style.transform='translateY(0)'">
                                    <strong style="color: #007bff;">🔗 ${store.store_name}</strong><br>
                                    <small style="color: #666;">Buscar: "${store.search_term}"</small>
                                </a>
                            `).join('')}
                        </div>

                        <div class="add-url-form" style="background: #fff3cd; padding: 15px; border-radius: 5px;">
                            <h4>➕ Añadir producto encontrado</h4>
                            <div style="display: flex; gap: 10px; margin-top: 10px;">
                                <input type="url" id="newProductUrl" placeholder="https://..."
                                    style="flex: 1; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
                                <input type="number" id="newProductTargetPrice" placeholder="Precio objetivo"
                                    style="width: 120px; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
                                <button onclick="crossSiteSearch.addFoundProduct()"
                                    style="padding: 8px 20px; background: #28a745; color: white; border: none; border-radius: 4px; cursor: pointer;">
                                    Añadir
                                </button>
                            </div>
                        </div>
                    </div>

                    <div style="margin-top: 20px; text-align: center;">
                        <button onclick="crossSiteSearch.viewComparison(${data.group_id})"
                            style="padding: 10px 30px; background: #007bff; color: white; border: none; border-radius: 4px; cursor: pointer; font-size: 16px;">
                            📊 Ver Comparación de Precios
                        </button>
                    </div>
                </div>
            `;

            document.getElementById('searchResults').innerHTML = resultsHtml;

        } catch (error) {
            document.getElementById('searchResults').innerHTML = `
                <div class="error" style="background: #f8d7da; padding: 15px; border-radius: 5px; color: #721c24;">
                    <strong>❌ Error:</strong> ${error.message}
                </div>
            `;
        }
    }

    /**
     * Añadir producto encontrado al grupo
     */
    async addFoundProduct() {
        const url = document.getElementById('newProductUrl').value.trim();
        const targetPrice = document.getElementById('newProductTargetPrice').value || 0;

        if (!url) {
            alert('Por favor introduce una URL');
            return;
        }

        if (!this.currentGroupId) {
            alert('Error: No hay grupo activo');
            return;
        }

        try {
            await this.addToGroup(this.currentGroupId, url, targetPrice);

            // Limpiar campos
            document.getElementById('newProductUrl').value = '';
            document.getElementById('newProductTargetPrice').value = '';

            // Mostrar mensaje de éxito
            alert('✅ Producto añadido al grupo. Se extraerán los datos automáticamente.');

            // Recargar comparación
            this.viewComparison(this.currentGroupId);

        } catch (error) {
            alert('Error añadiendo producto: ' + error.message);
        }
    }

    /**
     * Ver comparación de precios del grupo
     */
    async viewComparison(groupId) {
        try {
            const data = await this.getComparison(groupId);

            // Cerrar modal actual
            const modal = document.getElementById('crossSiteModal');
            if (modal) {
                modal.remove();
            }

            // Mostrar modal de comparación
            this.showComparisonModal(data);

        } catch (error) {
            alert('Error obteniendo comparación: ' + error.message);
        }
    }

    /**
     * Mostrar modal de comparación de precios
     */
    showComparisonModal(data) {
        const products = data.products || [];
        const stats = data.stats || {};

        // Encontrar el mejor precio
        const bestPrice = products.length > 0 ? Math.min(...products.filter(p => p.current_price).map(p => parseFloat(p.current_price))) : 0;

        const modalHtml = `
            <div id="comparisonModal" class="modal" style="display: block;">
                <div class="modal-content" style="max-width: 1000px;">
                    <span class="close" onclick="document.getElementById('comparisonModal').remove()">&times;</span>
                    <h2>📊 Comparación de Precios</h2>

                    ${stats.product_name ? `
                        <div class="stats-summary" style="background: #e7f3ff; padding: 15px; border-radius: 5px; margin-bottom: 20px;">
                            <h3>${stats.product_name}</h3>
                            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; margin-top: 10px;">
                                <div>
                                    <strong>💰 Mejor precio:</strong><br>
                                    <span style="color: #28a745; font-size: 24px; font-weight: bold;">${stats.best_price ? stats.best_price + '€' : 'N/A'}</span>
                                </div>
                                <div>
                                    <strong>📈 Precio más alto:</strong><br>
                                    <span style="font-size: 20px;">${stats.highest_price ? stats.highest_price + '€' : 'N/A'}</span>
                                </div>
                                <div>
                                    <strong>📊 Precio medio:</strong><br>
                                    <span style="font-size: 20px;">${stats.avg_price ? parseFloat(stats.avg_price).toFixed(2) + '€' : 'N/A'}</span>
                                </div>
                                <div>
                                    <strong>🏪 Tiendas:</strong><br>
                                    <span style="font-size: 20px;">${stats.store_count || 0}</span>
                                </div>
                            </div>
                        </div>
                    ` : ''}

                    <div class="products-comparison">
                        <h3>Productos Encontrados (${products.length})</h3>
                        ${products.length === 0 ? `
                            <p style="text-align: center; color: #666; padding: 40px;">
                                No se han añadido productos aún. Usa el botón "Buscar en otras tiendas" para encontrar productos similares.
                            </p>
                        ` : `
                            <div class="products-grid" style="display: grid; gap: 15px;">
                                ${products.map(product => {
                                    const price = parseFloat(product.current_price);
                                    const isBestPrice = price === bestPrice;

                                    return `
                                        <div class="product-comparison-card" style="
                                            display: flex;
                                            gap: 15px;
                                            padding: 15px;
                                            background: white;
                                            border: 2px solid ${isBestPrice ? '#28a745' : '#ddd'};
                                            border-radius: 8px;
                                            ${isBestPrice ? 'box-shadow: 0 4px 12px rgba(40, 167, 69, 0.2);' : ''}
                                        ">
                                            ${product.product_image ? `
                                                <img src="${product.product_image}" alt="${product.store_product_name}"
                                                    style="width: 100px; height: 100px; object-fit: contain; border-radius: 4px;">
                                            ` : `
                                                <div style="width: 100px; height: 100px; background: #f0f0f0; border-radius: 4px; display: flex; align-items: center; justify-content: center;">
                                                    📦
                                                </div>
                                            `}

                                            <div style="flex: 1;">
                                                <div style="display: flex; justify-content: space-between; align-items: start; margin-bottom: 10px;">
                                                    <div>
                                                        <strong style="color: #007bff;">${product.store_name}</strong>
                                                        ${isBestPrice ? '<span style="background: #28a745; color: white; padding: 2px 8px; border-radius: 3px; font-size: 12px; margin-left: 10px;">MEJOR PRECIO</span>' : ''}
                                                        ${product.is_primary ? '<span style="background: #ffc107; color: #000; padding: 2px 8px; border-radius: 3px; font-size: 12px; margin-left: 10px;">ORIGINAL</span>' : ''}
                                                    </div>
                                                    <div style="text-align: right;">
                                                        <div style="font-size: 24px; font-weight: bold; color: ${isBestPrice ? '#28a745' : '#333'};">
                                                            ${product.current_price ? product.current_price + '€' : 'Pendiente'}
                                                        </div>
                                                        ${product.product_discount ? `
                                                            <div style="color: #dc3545; font-weight: bold;">
                                                                -${product.product_discount}%
                                                            </div>
                                                        ` : ''}
                                                    </div>
                                                </div>

                                                <p style="color: #666; font-size: 14px; margin: 5px 0;">
                                                    ${product.store_product_name || 'Cargando información...'}
                                                </p>

                                                <div style="display: flex; gap: 10px; margin-top: 10px;">
                                                    <a href="${product.url}" target="_blank"
                                                        style="padding: 6px 12px; background: #007bff; color: white; text-decoration: none; border-radius: 4px; font-size: 14px;">
                                                        🔗 Ver en tienda
                                                    </a>
                                                    ${product.last_checked ? `
                                                        <span style="color: #666; font-size: 12px; align-self: center;">
                                                            Actualizado: ${new Date(product.last_checked).toLocaleString('es-ES')}
                                                        </span>
                                                    ` : ''}
                                                </div>
                                            </div>
                                        </div>
                                    `;
                                }).join('')}
                            </div>
                        `}
                    </div>

                    <div style="margin-top: 20px; text-align: center;">
                        <button onclick="document.getElementById('comparisonModal').remove()"
                            style="padding: 10px 30px; background: #6c757d; color: white; border: none; border-radius: 4px; cursor: pointer;">
                            Cerrar
                        </button>
                    </div>
                </div>
            </div>
        `;

        document.body.insertAdjacentHTML('beforeend', modalHtml);
    }
}

// Instancia global
const crossSiteSearch = new CrossSiteSearch();
