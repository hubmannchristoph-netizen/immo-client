<?php
/**
 * Filter-Bar.
 * Die Feldnamen entsprechen den Query-Parametern der ImmoManager-REST-API.
 */

if (!defined('ABSPATH')) {
    exit;
}
?>
<div class="immo-filters" style="margin-bottom: 30px; padding: 20px; background: #f9f9f9; border-radius: 8px;">
    <form class="immo-filter-form" style="display: flex; gap: 20px; flex-wrap: wrap;">
        <div class="filter-group">
            <label style="display:block;font-size:.8em;margin-bottom:5px;">Status</label>
            <select name="status">
                <option value="">Alle Status</option>
                <option value="available">Verfügbar</option>
                <option value="reserved">Reserviert</option>
                <option value="sold">Verkauft</option>
                <option value="rented">Vermietet</option>
            </select>
        </div>

        <div class="filter-group">
            <label style="display:block;font-size:.8em;margin-bottom:5px;">Modus</label>
            <select name="mode">
                <option value="">Alle</option>
                <option value="sale">Kauf</option>
                <option value="rent">Miete</option>
            </select>
        </div>

        <div class="filter-group">
            <label style="display:block;font-size:.8em;margin-bottom:5px;">Zimmer (min)</label>
            <select name="rooms">
                <option value="">Egal</option>
                <option value="1,2,3,4,5,6,7,8">1+</option>
                <option value="2,3,4,5,6,7,8">2+</option>
                <option value="3,4,5,6,7,8">3+</option>
                <option value="4,5,6,7,8">4+</option>
            </select>
        </div>

        <div class="filter-group">
            <label style="display:block;font-size:.8em;margin-bottom:5px;">Max. Preis</label>
            <select name="price_max">
                <option value="">Egal</option>
                <option value="250000">bis 250.000 &euro;</option>
                <option value="500000">bis 500.000 &euro;</option>
                <option value="750000">bis 750.000 &euro;</option>
                <option value="1000000">bis 1.000.000 &euro;</option>
            </select>
        </div>

        <div class="filter-group">
            <label style="display:block;font-size:.8em;margin-bottom:5px;">Sortierung</label>
            <select name="orderby">
                <option value="newest">Neueste zuerst</option>
                <option value="price_asc">Preis aufsteigend</option>
                <option value="price_desc">Preis absteigend</option>
                <option value="area_desc">Fläche absteigend</option>
            </select>
        </div>
    </form>
</div>
