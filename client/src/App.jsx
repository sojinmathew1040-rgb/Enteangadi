import { useState, useEffect } from 'react';
import { apiFetch, getBaseUrl } from './utils/apiClient';
import './App.css';

function App() {
  const [products, setProducts] = useState([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);

  // Search and filter states
  const [searchTerm, setSearchTerm] = useState('');
  const [categoryFilter, setCategoryFilter] = useState('');
  const [adTypeFilter, setAdTypeFilter] = useState('');
  const [minPrice, setMinPrice] = useState('');
  const [maxPrice, setMaxPrice] = useState('');
  const [categories, setCategories] = useState([]);

  // Selected product for detailed modal view
  const [selectedProduct, setSelectedProduct] = useState(null);

  // Quick test for backend connection URL
  const backendUrl = getBaseUrl();

  // Load products & categories from API
  const fetchProducts = async () => {
    setLoading(true);
    setError(null);
    try {
      // Build query string
      const params = new URLSearchParams();
      if (searchTerm) params.append('search', searchTerm);
      if (categoryFilter) params.append('category_id', categoryFilter);
      if (adTypeFilter) params.append('ad_type', adTypeFilter);
      if (minPrice) params.append('min_price', minPrice);
      if (maxPrice) params.append('max_price', maxPrice);

      const queryString = params.toString() ? `?${params.toString()}` : '';
      const response = await apiFetch(`/api/products.php${queryString}`);

      if (response.success) {
        setProducts(response.products || []);
      } else {
        throw new Error(response.message || 'Failed to fetch listings');
      }
    } catch (err) {
      console.error(err);
      setError(err.message || 'Could not connect to the backend server. Make sure your local PHP server (Apache) is running.');
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    fetchProducts();
  }, [categoryFilter, adTypeFilter]);

  // Clean formatted price helper
  const formatPrice = (price) => {
    return new Intl.NumberFormat('en-IN', {
      style: 'currency',
      currency: 'INR',
      maximumFractionDigits: 0
    }).format(price);
  };

  // Human readable dates helper
  const formatDate = (dateStr) => {
    if (!dateStr) return '';
    const date = new Date(dateStr);
    return date.toLocaleDateString('en-IN', { day: 'numeric', month: 'short' });
  };

  const handleSearchSubmit = (e) => {
    e.preventDefault();
    fetchProducts();
  };

  const resetFilters = () => {
    setSearchTerm('');
    setCategoryFilter('');
    setAdTypeFilter('');
    setMinPrice('');
    setMaxPrice('');
    fetchProducts();
  };

  return (
    <div className="app-container">
      {/* Header Navigation */}
      <header className="app-header">
        <div className="logo-section">
          <div className="app-icon">🛍️</div>
          <div>
            <h1 className="logo-title">Enteangadi</h1>
            <p className="logo-subtitle">Local Marketplace App</p>
          </div>
        </div>
        <div className="connection-badge">
          <span className="badge-dot"></span>
          Connected to {backendUrl}
        </div>
      </header>

      {/* Main Content Layout */}
      <main className="app-main">
        {/* Search & Filters Column */}
        <aside className="filters-sidebar">
          <h2 className="section-title">Search & Filters</h2>
          <form onSubmit={handleSearchSubmit} className="filters-form">
            <div className="form-group">
              <label>Search Listings</label>
              <div className="search-input-wrapper">
                <input
                  type="text"
                  placeholder="What are you looking for?"
                  value={searchTerm}
                  onChange={(e) => setSearchTerm(e.target.value)}
                  className="input-field"
                />
                <button type="submit" className="search-btn">🔍</button>
              </div>
            </div>

            <div className="form-group">
              <label>Deal Type</label>
              <div className="segmented-control">
                <button
                  type="button"
                  className={adTypeFilter === '' ? 'active' : ''}
                  onClick={() => setAdTypeFilter('')}
                >
                  All
                </button>
                <button
                  type="button"
                  className={adTypeFilter === 'sell' ? 'active' : ''}
                  onClick={() => setAdTypeFilter('sell')}
                >
                  For Sale
                </button>
                <button
                  type="button"
                  className={adTypeFilter === 'buy' ? 'active' : ''}
                  onClick={() => setAdTypeFilter('buy')}
                >
                  Wanted
                </button>
              </div>
            </div>

            <div className="form-group">
              <label>Price Range</label>
              <div className="price-inputs">
                <input
                  type="number"
                  placeholder="Min ₹"
                  value={minPrice}
                  onChange={(e) => setMinPrice(e.target.value)}
                  className="input-field price-field"
                />
                <span>to</span>
                <input
                  type="number"
                  placeholder="Max ₹"
                  value={maxPrice}
                  onChange={(e) => setMaxPrice(e.target.value)}
                  className="input-field price-field"
                />
              </div>
            </div>

            <div className="filter-actions">
              <button type="button" onClick={fetchProducts} className="apply-btn">
                Apply Filters
              </button>
              <button type="button" onClick={resetFilters} className="clear-btn">
                Reset
              </button>
            </div>
          </form>
        </aside>

        {/* Listings Section */}
        <section className="listings-section">
          {loading ? (
            <div className="status-container">
              <div className="spinner"></div>
              <p>Fetching amazing local listings...</p>
            </div>
          ) : error ? (
            <div className="status-container error-state">
              <div className="error-icon">⚠️</div>
              <h3>Connection Issues</h3>
              <p>{error}</p>
              <button onClick={fetchProducts} className="retry-btn">Retry Connection</button>
            </div>
          ) : products.length === 0 ? (
            <div className="status-container empty-state">
              <div className="empty-icon">📭</div>
              <h3>No Listings Found</h3>
              <p>We couldn't find any listings matching your active filters. Try broadening your criteria or reset the search.</p>
              <button onClick={resetFilters} className="retry-btn">Clear All Filters</button>
            </div>
          ) : (
            <div className="listings-grid">
              {products.map((product) => (
                <div
                  key={product.id}
                  className="product-card"
                  onClick={() => setSelectedProduct(product)}
                >
                  <div className="card-image-wrapper">
                    {product.main_image ? (
                      <img
                        src={`${backendUrl}/${product.main_image}`}
                        alt={product.title}
                        className="card-image"
                        onError={(e) => {
                          e.target.onerror = null;
                          e.target.src = 'https://images.unsplash.com/photo-1542838132-92c53300491e?auto=format&fit=crop&q=80&w=400';
                        }}
                      />
                    ) : (
                      <div className="placeholder-image">
                        📦
                      </div>
                    )}
                    <span className={`ad-type-badge ${product.type}`}>
                      {product.type === 'sell' ? 'FOR SALE' : 'WANTED'}
                    </span>
                  </div>

                  <div className="card-info">
                    <span className="card-category">{product.category_name || 'Marketplace'}</span>
                    <h3 className="card-title">{product.title}</h3>
                    <div className="card-meta">
                      <span className="card-price">{formatPrice(product.price)}</span>
                      <span className="card-date">{formatDate(product.created_at)}</span>
                    </div>
                    {product.location_name && (
                      <div className="card-location">
                        📍 {product.location_name.split(',')[0]}
                      </div>
                    )}
                  </div>
                </div>
              ))}
            </div>
          )}
        </section>
      </main>

      {/* Product Detail Modal */}
      {selectedProduct && (
        <div className="modal-overlay" onClick={() => setSelectedProduct(null)}>
          <div className="modal-content" onClick={(e) => e.stopPropagation()}>
            <button className="modal-close" onClick={() => setSelectedProduct(null)}>×</button>

            <div className="modal-body">
              <div className="modal-gallery">
                {selectedProduct.main_image ? (
                  <img
                    src={`${backendUrl}/${selectedProduct.main_image}`}
                    alt={selectedProduct.title}
                    className="modal-main-image"
                    onError={(e) => {
                      e.target.onerror = null;
                      e.target.src = 'https://images.unsplash.com/photo-1542838132-92c53300491e?auto=format&fit=crop&q=80&w=800';
                    }}
                  />
                ) : (
                  <div className="modal-placeholder-image">📦 No Image Available</div>
                )}
              </div>

              <div className="modal-details">
                <div className="modal-header-meta">
                  <span className={`ad-type-badge ${selectedProduct.type}`}>
                    {selectedProduct.type === 'sell' ? 'FOR SALE' : 'WANTED'}
                  </span>
                  <span className="modal-id">Ref: {selectedProduct.unique_id || `ID-${selectedProduct.id}`}</span>
                </div>

                <h2 className="modal-title">{selectedProduct.title}</h2>
                <div className="modal-price">{formatPrice(selectedProduct.price)}</div>

                <hr className="divider" />

                <h4 className="details-heading">Description</h4>
                <p className="modal-desc">{selectedProduct.description || 'No description provided by seller.'}</p>

                <h4 className="details-heading">Listing Metrics & Information</h4>
                <div className="metrics-grid">
                  <div className="metric-box">
                    <span className="metric-label">Views</span>
                    <span className="metric-val">👁️ {selectedProduct.views || 0}</span>
                  </div>
                  <div className="metric-box">
                    <span className="metric-label">Listed on</span>
                    <span className="metric-val">📅 {formatDate(selectedProduct.created_at)}</span>
                  </div>
                </div>

                {selectedProduct.location_name && (
                  <>
                    <h4 className="details-heading">Location</h4>
                    <p className="modal-location">📍 {selectedProduct.location_name}</p>
                    {selectedProduct.latitude && selectedProduct.longitude && (
                      <a
                        href={`https://www.google.com/maps/search/?api=1&query=${selectedProduct.latitude},${selectedProduct.longitude}`}
                        target="_blank"
                        rel="noopener noreferrer"
                        className="map-link-btn"
                      >
                        🗺️ View on Google Maps
                      </a>
                    )}
                  </>
                )}

                <hr className="divider" />

                <div className="contact-methods">
                  {selectedProduct.phone_number && (
                    <a href={`tel:${selectedProduct.phone_number}`} className="contact-btn call-btn">
                      📞 Call Seller ({selectedProduct.phone_number})
                    </a>
                  )}
                  {selectedProduct.whatsapp_number && (
                    <a
                      href={`https://wa.me/${selectedProduct.whatsapp_number.replace(/\D/g, '')}?text=Hi,%20I'm%20interested%20in%20your%20listing:%20${encodeURIComponent(selectedProduct.title)}`}
                      target="_blank"
                      rel="noopener noreferrer"
                      className="contact-btn whatsapp-btn"
                    >
                      💬 Chat on WhatsApp
                    </a>
                  )}
                </div>
              </div>
            </div>
          </div>
        </div>
      )}
    </div>
  );
}

export default App;
