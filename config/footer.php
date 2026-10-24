<footer class="site-footer">
  <div class="site-footer-inner">

    <!-- Col 1 — Brand -->
    <div class="footer-col footer-brand">
      <a href="index.php" class="footer-logo">
        <img src="logo.jpg" alt="Gadget Genesis" style="height:48px;width:auto;"/>
      </a>
      <p class="footer-tagline">Your trusted tech store in Kampala. Quality gadgets, great prices, fast delivery.</p>
      <div class="footer-social">
        <a href="https://instagram.com/gadgetgenesis" target="_blank" title="Instagram" class="fsoc fsoc-ig">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="16" height="16"><rect x="2" y="2" width="20" height="20" rx="5" ry="5"/><circle cx="12" cy="12" r="4"/><circle cx="17.5" cy="6.5" r="1" fill="currentColor" stroke="none"/></svg>
        </a>
        <a href="https://facebook.com/gadgetgenesis" target="_blank" title="Facebook" class="fsoc fsoc-fb">
          <svg viewBox="0 0 24 24" fill="currentColor" width="16" height="16"><path d="M18 2h-3a5 5 0 0 0-5 5v3H7v4h3v8h4v-8h3l1-4h-4V7a1 1 0 0 1 1-1h3z"/></svg>
        </a>
        <a href="https://snapchat.com/add/gadgetgenesis" target="_blank" title="Snapchat" class="fsoc fsoc-sc">
          <svg viewBox="0 0 24 24" fill="currentColor" width="16" height="16"><path d="M12 2C8.5 2 6 4.5 6 8v1.5c-.8.2-1.5.5-1.5 1s.3.8.8 1c-.2.5-.5 1-.9 1.3-.2.1-.3.3-.1.5.4.5 1.2.7 2.2.8.3.5.7 1 1.5 1 .5 0 1-.2 1.5-.3.3-.1.5-.1.5-.1s.2 0 .5.1c.5.2 1 .3 1.5.3.8 0 1.2-.5 1.5-1 1-.1 1.8-.3 2.2-.8.2-.2.1-.4-.1-.5-.4-.3-.7-.8-.9-1.3.5-.2.8-.5.8-1s-.7-.8-1.5-1V8c0-3.5-2.5-6-6-6z"/></svg>
        </a>
        <a href="https://x.com/gadgetgenesis" target="_blank" title="X (Twitter)" class="fsoc fsoc-x">
          <svg viewBox="0 0 24 24" fill="currentColor" width="16" height="16"><path d="M18.244 2.25h3.308l-7.227 8.26 8.502 11.24H16.17l-5.214-6.817L4.99 21.75H1.68l7.73-8.835L1.254 2.25H8.08l4.713 6.231zm-1.161 17.52h1.833L7.084 4.126H5.117z"/></svg>
        </a>
      </div>
    </div>

    <!-- Col 2 — Shop -->
    <div class="footer-col">
      <h4>Shop</h4>
      <ul>
        <li><a href="shop.php">All Products</a></li>
        <li><a href="shop.php?collection=Smartphones">Smartphones</a></li>
        <li><a href="shop.php?collection=Audio">Audio</a></li>
        <li><a href="shop.php?collection=Smartwatches">Smartwatches</a></li>
        <li><a href="shop.php?collection=Laptops">Laptops</a></li>
        <li><a href="shop.php?collection=Accessories">Accessories</a></li>
        <li><a href="shop.php?collection=Deals" class="footer-deals-link">🔥 Deals</a></li>
      </ul>
    </div>

    <!-- Col 3 — Help -->
    <div class="footer-col">
      <h4>Help</h4>
      <ul>
        <li><a href="cart.php">My Cart</a></li>
        <li><a href="account.php">My Account</a></li>
        <li><a href="orders.php">Track My Order</a></li>
        <li><a href="info.php?page=return-policy">Return Policy</a></li>
        <li><a href="info.php?page=warranty">Warranty</a></li>
        <li><a href="info.php?page=faq">FAQ</a></li>
      </ul>
    </div>

    <!-- Col 4 — Contact -->
    <div class="footer-col">
      <h4>Visit Us</h4>
      <ul class="footer-contact">
        <li>
          <i data-lucide="map-pin"></i>
          <a href="https://maps.google.com/?q=Kooki+Towers+Buganda+Road+Kampala" target="_blank">
            Opposite CPS, Kooki Towers,<br>Buganda Rd, Kampala
          </a>
        </li>
        <li>
          <i data-lucide="phone"></i>
          <a href="tel:0759809061">0759 809061</a>
        </li>
        <li>
          <i data-lucide="message-circle"></i>
          <a href="https://wa.me/256759809061" target="_blank">WhatsApp Us</a>
        </li>
        <li>
          <i data-lucide="clock"></i>
          <span>Mon – Sat: 9am – 6pm</span>
        </li>
      </ul>
    </div>

    <!-- Col 5 — Newsletter -->
    <div class="footer-col footer-newsletter">
      <h4>Newsletter</h4>
      <p style="font-size:13px;color:rgba(255,255,255,.6);margin-bottom:14px;">Get exclusive deals and new arrivals straight to your inbox.</p>
      <form id="newsletterForm" style="display:flex;gap:8px;flex-wrap:wrap;">
        <input type="email" id="newsletterEmail" placeholder="Your email"
               style="flex:1;min-width:140px;padding:10px 14px;border:1.5px solid rgba(255,255,255,.15);border-radius:8px;background:rgba(255,255,255,.08);color:#fff;font-size:13px;font-family:inherit;outline:none;"/>
        <button type="submit" style="padding:10px 18px;background:#2563eb;color:#fff;border:none;border-radius:8px;font-size:13px;font-weight:700;cursor:pointer;font-family:inherit;white-space:nowrap;">Subscribe</button>
      </form>
      <p id="newsletterMsg" style="font-size:12px;margin-top:8px;display:none;"></p>
    </div>

  </div>

  <div class="footer-bottom">
    <p>&copy; <?= date('Y') ?> Gadget Genesis. All rights reserved.</p>
    <p>Kooki Towers, Buganda Rd, Kampala &nbsp;·&nbsp; 0759 809061</p>
  </div>
</footer>
