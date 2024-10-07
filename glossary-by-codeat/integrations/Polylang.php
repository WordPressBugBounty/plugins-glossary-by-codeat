<?php

/**
 * Glossary
 *
 * @package   Glossary
 * @author  Codeat <support@codeat.co>
 * @copyright 2020
 * @license   GPL 3.0+
 * @link      https://codeat.co
 */
namespace Glossary\Integrations;

use Glossary\Engine;
/**
 * Improve the elementor integration
 */
class Polylang extends Engine\Base {
    /**
     * Initialize the class
     *
     * @since 1.0.0
     * @return bool
     */
    public function initialize() {
        parent::initialize();
        if ( \function_exists( 'pll_register_string' ) ) {
            $this->register_strings();
        }
        return true;
    }

    /**
     * Load translated strings for polylang
     *
     * @return void
     */
    public function register_strings() {
        pll_register_string( 'Singular Glossary Term', 'Glossary Term', GT_NAME );
        pll_register_string( 'Plural Glossary Terms', 'Glossary Terms', GT_NAME );
    }

}
