<?php
/** Human-readable WooCommerce price; never expose screen-reader description as a second price. */
defined('ABSPATH') || exit;
final class ARC_Price {
    private static function money($number): string {
        if($number === '' || $number === null) return '';
        $value = function_exists('wc_price') ? (string) wc_price((float)$number) : (string)$number;
        for($i=0;$i<3;$i++) $value=html_entity_decode($value,ENT_QUOTES|ENT_HTML5,'UTF-8');
        $value=wp_strip_all_tags($value);
        return trim((string)preg_replace('/[\x{00A0}\x{202F}\s]+/u',' ',$value));
    }
    public static function label($product): string {
        if(!$product || !is_object($product)) return '';
        // For variable products use actual WooCommerce tax-display-aware variation minimum/maximum.
        if($product->is_type('variable') && method_exists($product,'get_variation_price')) {
            $minimum=$product->get_variation_price('min',true);
            $maximum=$product->get_variation_price('max',true);
            if($minimum!=='' && $maximum!=='') {
                $first=self::money($minimum);$last=self::money($maximum);
                return $first===$last ? $first : ($first.' – '.$last);
            }
        }
        if($product->is_type('grouped')) {
            $price=(string)$product->get_price_html();
            $price=(string)preg_replace('~<span[^>]*class=["\'][^"\']*(?:screen-reader-text|woocommerce-price-suffix)[^"\']*["\'][^>]*>.*?</span>~is','',$price);
            for($i=0;$i<3;$i++)$price=html_entity_decode($price,ENT_QUOTES|ENT_HTML5,'UTF-8');
            return trim((string)preg_replace('/[\x{00A0}\x{202F}\s]+/u',' ',wp_strip_all_tags($price)));
        }
        $raw=$product->get_price();
        if($raw!=='' && $raw!==null) {
            if(function_exists('wc_get_price_to_display'))$raw=wc_get_price_to_display($product);
            return self::money($raw);
        }
        return 'Cena na dotaz';
    }
}
