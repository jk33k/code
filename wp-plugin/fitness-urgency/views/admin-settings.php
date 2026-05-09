<?php defined( 'ABSPATH' ) || exit; ?>
<div class="wrap fu-admin">
  <h1>DWC - Spots Left</h1>

  <?php if ( ! empty( $_GET['saved'] ) )   : ?><div class="notice notice-success is-dismissible"><p>Program saved.</p></div><?php endif; ?>
  <?php if ( ! empty( $_GET['deleted'] ) ) : ?><div class="notice notice-success is-dismissible"><p>Program deleted.</p></div><?php endif; ?>
  <?php if ( ! empty( $_GET['error'] ) )   : ?><div class="notice notice-error"><p><?php echo esc_html( urldecode( $_GET['error'] ) ); ?></p></div><?php endif; ?>

  <div class="fu-layout">

    <!-- ── Program list ─────────────────────────────────────────── -->
    <div class="fu-card">
      <h2>Programs</h2>
      <?php if ( empty( $programs ) ) : ?>
        <p><em>No programs yet. Add one below.</em></p>
      <?php else : ?>
        <table class="widefat striped">
          <thead>
            <tr>
              <th>Name</th><th>Slug</th><th>Dates</th><th>Default</th><th>Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ( $programs as $p ) : ?>
            <tr>
              <td><strong><?php echo esc_html( $p['name'] ); ?></strong></td>
              <td><code><?php echo esc_html( $p['slug'] ); ?></code></td>
              <td><?php echo esc_html( implode( ', ', $p['dates'] ) ); ?></td>
              <td><?php echo ! empty( $p['default'] ) ? '★' : ''; ?></td>
              <td>
                <a href="<?php echo esc_url( add_query_arg( [ 'page' => 'fitness-urgency', 'edit' => $p['slug'] ], admin_url( 'options-general.php' ) ) ); ?>">Edit</a>
                &nbsp;|&nbsp;
                <a href="<?php echo esc_url( wp_nonce_url( add_query_arg( [ 'action' => 'fu_delete', 'slug' => $p['slug'] ], admin_url( 'admin-post.php' ) ), 'fu_delete_' . $p['slug'] ) ); ?>"
                   onclick="return confirm('Delete \'<?php echo esc_js( $p['name'] ); ?>\'?');"
                   style="color:#b32d2e;">Delete</a>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      <?php endif; ?>
    </div>

    <!-- ── Add / Edit form ──────────────────────────────────────── -->
    <div class="fu-card">
      <h2><?php echo $editing ? 'Edit Program: ' . esc_html( $editing['name'] ) : 'Add Program'; ?></h2>

      <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
        <?php wp_nonce_field( 'fu_save_program' ); ?>
        <input type="hidden" name="action" value="fu_save">

        <table class="form-table">
          <tr>
            <th><label for="fu-name">Program name</label></th>
            <td>
              <input id="fu-name" name="name" type="text" class="regular-text"
                     value="<?php echo esc_attr( $editing['name'] ?? '' ); ?>" required>
              <p class="description">Displayed in the admin list only.</p>
            </td>
          </tr>
          <tr>
            <th><label for="fu-slug">Slug</label></th>
            <td>
              <input id="fu-slug" name="slug" type="text" class="regular-text"
                     value="<?php echo esc_attr( $editing['slug'] ?? '' ); ?>"
                     <?php echo $editing ? 'readonly' : ''; ?> required
                     pattern="[a-z0-9_-]+" title="Lowercase letters, numbers, hyphens and underscores only">
              <p class="description">
                Used in shortcodes, e.g. <code>[fu_card program="<?php echo esc_attr( $editing['slug'] ?? 'your-slug' ); ?>"]</code>.
                Cannot be changed once saved.
              </p>
            </td>
          </tr>
          <tr>
            <th>Start dates</th>
            <td>
              <div id="fu-dates-list">
                <?php
                $saved_dates = $editing['dates'] ?? [ '', '', '', '' ];
                // Always show at least 4 rows; pad if fewer saved.
                while ( count( $saved_dates ) < 4 ) $saved_dates[] = '';
                foreach ( $saved_dates as $i => $d ) :
                ?>
                <div class="fu-date-row">
                  <input name="dates[]" type="date" value="<?php echo esc_attr( $d ); ?>"
                         placeholder="YYYY-MM-DD" class="fu-date-input">
                  <button type="button" class="button fu-remove-date" <?php echo $i < 4 ? 'style="visibility:hidden"' : ''; ?>>✕</button>
                </div>
                <?php endforeach; ?>
              </div>
              <button type="button" class="button" id="fu-add-date">+ Add date</button>
              <p class="description">Each must be a Monday. The script shows the next 1–2 dates automatically.</p>
            </td>
          </tr>
          <tr>
            <th><label for="fu-hp-label">High-price label</label></th>
            <td>
              <input id="fu-hp-label" name="high_price_label" type="text" class="regular-text"
                     value="<?php echo esc_attr( $editing['high_price_label'] ?? 'Start next Monday' ); ?>">
              <p class="description">Text shown after the final date sells out (the date variable).</p>
            </td>
          </tr>
          <tr>
            <th><label for="fu-hp-spots">High-price spots</label></th>
            <td>
              <input id="fu-hp-spots" name="high_price_spots" type="number" min="1" max="99" class="small-text"
                     value="<?php echo esc_attr( $editing['high_price_spots'] ?? 2 ); ?>">
            </td>
          </tr>
          <tr>
            <th>Default program</th>
            <td>
              <label>
                <input name="default" type="checkbox" value="1"
                       <?php checked( ! empty( $editing['default'] ) ); ?>>
                Use as the default when <code>program=""</code> is omitted from shortcodes
              </label>
            </td>
          </tr>
        </table>

        <p class="submit">
          <button type="submit" class="button button-primary">
            <?php echo $editing ? 'Save changes' : 'Add program'; ?>
          </button>
          <?php if ( $editing ) : ?>
          <a href="<?php echo esc_url( add_query_arg( 'page', 'fitness-urgency', admin_url( 'options-general.php' ) ) ); ?>"
             class="button">Cancel</a>
          <?php endif; ?>
        </p>
      </form>
    </div>

    <!-- ── Shortcode reference ──────────────────────────────────── -->
    <div class="fu-card">
      <h2>Shortcode reference</h2>
      <table class="widefat">
        <thead><tr><th>Shortcode</th><th>Output</th></tr></thead>
        <tbody>
          <tr><td><code>[fu_card]</code></td><td>Full two-card urgency block</td></tr>
          <tr><td><code>[fu_startdate slot="1"]</code></td><td>First start date (e.g. "Monday, June 8th")</td></tr>
          <tr><td><code>[fu_startdate slot="2"]</code></td><td>Second start date text</td></tr>
          <tr><td><code>[fu_spotsleft slot="1"]</code></td><td>First spots-left text (or SOLD OUT)</td></tr>
          <tr><td><code>[fu_spotsleft slot="2"]</code></td><td>Second spots-left text</td></tr>
          <tr><td><code>[fu_finaldate]</code></td><td>Last program date as "June 29th"</td></tr>
          <tr><td><code>[fu_show_if_slot slot="2"]…[/fu_show_if_slot]</code></td><td>Shown only when slot 2 has data</td></tr>
          <tr><td><code>[fu_show_phase phase="high_demand"]…[/fu_show_phase]</code></td><td>Shown during the high-demand window</td></tr>
          <tr><td><code>[fu_show_phase phase="final_week"]…[/fu_show_phase]</code></td><td>Shown during the final-week window</td></tr>
          <tr><td><code>[fu_countdown]</code></td><td>Live D/H/M/S countdown to final date</td></tr>
        </tbody>
      </table>
      <p class="description" style="margin-top:8px;">
        Add <code>program="slug"</code> to any shortcode to target a specific program.
        Omit it to use the default program.
      </p>
    </div>

  </div><!-- .fu-layout -->
</div>

<script>
(function() {
  document.getElementById('fu-add-date').addEventListener('click', function() {
    var list = document.getElementById('fu-dates-list');
    var row  = document.createElement('div');
    row.className = 'fu-date-row';
    row.innerHTML  = '<input name="dates[]" type="date" placeholder="YYYY-MM-DD" class="fu-date-input">'
                   + '<button type="button" class="button fu-remove-date">✕</button>';
    list.appendChild(row);
  });
  document.getElementById('fu-dates-list').addEventListener('click', function(e) {
    if (e.target.classList.contains('fu-remove-date')) {
      e.target.closest('.fu-date-row').remove();
    }
  });
})();
</script>
