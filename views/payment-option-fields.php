<?php /**
 * @var string $gateway
 * @var array $values
*/ ?>
<tr class="pmpro_settings_divider gateway gateway_hutko"
    <?php if ($gateway != "hutko") { ?>style="display: none;"<?php } ?>>
    <td colspan="2">
        <?php _e('Hutko Settings', 'pmp-hutko-payment'); ?>
    </td>
</tr>
<tr class="gateway gateway_hutko" <?php if ($gateway != "hutko") { ?>style="display: none;"<?php } ?>>
    <th scope="row" valign="top">
        <label for="hutko_merchantid"><?php _e('Merchant ID', 'pmp-hutko-payment'); ?>:</label>
    </th>
    <td>
        <input type="text" id="hutko_merchantid" name="hutko_merchantid" size="60"
               value="<?php echo esc_attr($values['hutko_merchantid']) ?>"/>
    </td>
</tr>
<tr class="gateway gateway_hutko" <?php if ($gateway != "hutko") { ?>style="display: none;"<?php } ?>>
    <th scope="row" valign="top">
        <label for="hutko_securitykey"><?php _e('Payment key', 'pmp-hutko-payment'); ?>:</label>
    </th>
    <td>
        <textarea id="hutko_securitykey" name="hutko_securitykey" rows="3"
                  cols="80"><?php echo esc_textarea($values['hutko_securitykey']); ?></textarea>
    </td>
</tr>