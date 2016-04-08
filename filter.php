<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Chemistry file filter for ChemDoodle and JMol processing and display.
 *
 * @package    filter_chemrender
 * @copyright  2016 UC Regents
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
defined('MOODLE_INTERNAL') || die();

/**
 * ChemDoodle and JMol filter.
 *
 * @package    filter_chemrender
 * @copyright  2016 UC Regents
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class filter_chemrender extends moodle_text_filter {

    /**
     * This filter will replace specified chemistry data file links with the
     * appropriate ChemDoodle or JMol rendered object.
     *
     * @param string $text
     * @param array $options
     * @return string
     */
    public function filter($text, array $options = array()) {
        // Global declaration in case ChemDoodle or YUI JSmol module is inserted elsewhere in page.
        global $CFG, $chemdoodlehasbeeninitialized, $jsmolhasbeenconfigured;
        $wwwroot = $CFG->wwwroot;
        $host = preg_replace('~^.*://([^:/]*).*$~', '$1', $wwwroot);

        // Filter through Chemdoodle.
        // Edit $chemdoodlefiletypes to add/remove chemical structure file types that can be displayed.
        // Filetypes: https://web.chemdoodle.com/tutorial/loading-data/.
        $chemdoodlefiletypes = 'mol|jdx|cml';

        $search = '/<a\\b([^>]*?)href=\"((?:\.|\\\|https?:\/\/' . $host .
                ')[^\"]+\.(' . $chemdoodlefiletypes . '))\??(.*?)\"([^>]*)>(.*?)<\/a>(\s*JMOLSCRIPT\{(.*?)\})?/is';

        $chemdoodletext = preg_replace_callback($search, array($this, 'filter_chemrender_chemdoodle_replace_callback'), $text);

        if (($chemdoodletext != $text) && !isset($chemdoodlehasbeeninitialized)) {
            $chemdoodlehasbeeninitialized = true;
            $chemdoodletext = '<link rel="stylesheet" href="' . $wwwroot . '/filter/chemrender/lib/chemdoodle/ChemDoodleWeb.css" type="text/css" />
                    <script src="' . $wwwroot . '/filter/chemrender/lib/chemdoodle/ChemDoodleWeb.js" type="text/javascript"></script>
                    <script src="' . $wwwroot . '/filter/chemrender/module.js" type="text/javascript"></script>'
                    . $chemdoodletext;
        }

        // Filter through JMol.
        // Edit $jmolfiletypes to add/remove chemical structure file types that can be displayed.
        // Filetypes: http://wiki.jmol.org/index.php/File_formats.
        $jmolfiletypes = 'cif|cml|csmol|mol|mol2|pdb\.gz|pdb|pse|sdf|xyz';

        $search = '/<a\\b([^>]*?)href=\"((?:\.|\\\|https?:\/\/' . $host .
                ')[^\"]+\.(' . $jmolfiletypes . '))\??(.*?)\"([^>]*)>(.*?)<\/a>(\s*JMOLSCRIPT\{(.*?)\})?/is';

        $jmoltext = preg_replace_callback($search, array($this, 'filter_chemrender_jmol_replace_callback'), $chemdoodletext);

        // YUI JSmol module configured once per page.
        if (($jmoltext != $chemdoodletext) && !isset($jsmolhasbeenconfigured)) {
            $jsmolhasbeenconfigured = true;
            $jmoltext = '<script type="text/javascript">
                    YUI().applyConfig({
                        modules: {
                            "jsmol": {
                                fullpath: "/filter/chemrender/lib/jsmol/JSmol.min.js"
                            }
                        }
                    });
                    </script>'
                    . $jmoltext;
        }

        return $jmoltext;
    }

    /**
     * Use this function to pull out the query array elements.
     *
     * @param string $var
     * @return array
     */
    public function filter_chemrender_parse_query($var) {
        $var = html_entity_decode($var);
        $var = rawurldecode($var);
        $var = explode('&', $var);
        $arr = array();

        foreach ($var as $val) {
            $x = explode('=', $val, 2);
            if (count($x) == 2) {
                $arr[$x[0]] = $x[1];
            }
        }

        unset($val, $x, $var);

        return $arr;
    }

    /**
     * Replaces ChemDoodle text.
     *
     * @param array $matches
     * @return string
     */
    public function filter_chemrender_chemdoodle_replace_callback($matches) {
        global $CFG;
        $wwwroot = $CFG->wwwroot;
        $a = uniqid();
        $fileurl = $matches[2];
        $extension = $matches[3];
        $queryarray = $this->filter_chemrender_parse_query($matches[4]);

        if (array_key_exists('height', $queryarray)) {
            $height = $queryarray['height'];
        } else {
            $height = 325;
        }
        if (array_key_exists('width', $queryarray)) {
            $width = $queryarray['width'];
        } else {
            $width = 325;
        }
        // Do not process if JMol is specified.
        if (array_key_exists('renderer', $queryarray)) {
            $renderer = $queryarray['renderer'];
            if ($renderer == 'jmol') {
                return $matches[0];
            }
        }
        if (array_key_exists('xaxis', $queryarray)) {
            $xaxis = $queryarray['xaxis'];
        } else {
            $xaxis = 'x-axis';
        }
        if (array_key_exists('yaxis', $queryarray)) {
            $yaxis = $queryarray['yaxis'];
        } else {
            $yaxis = 'y-axis';
        }
        $helplink = "";
        $chemdoodlespectrum = get_string('chemdoodlespectrum', 'filter_chemrender');
        switch ($extension) {
            case "jdx":
                /*
                 * IUPAC JCAMP-DX Files – contain spectral data for mass spectrometry, NMR spectroscopy and IR spectroscopy.
                 * They end with a .jcamp or .jdx extension. Use the ChemDoodle.readJCAMP() function to parse this data and return a Spectrum data structure.
                 */
                if ($height > 0 && $width > 0) {
                    $jscall = "var spectrum$a = ChemDoodle.readJCAMP(jdxstring);
                                var component$a = new ChemDoodle.PerspectiveCanvas('component$a', $width, $height);
                                component$a.specs.text_font_families = ['Lato','Helvetica Neue','Helvetica','Arial','sans-serif'];
                                component$a.specs.text_font_size = 14;
                                spectrum$a.xUnit = '$xaxis';
                                spectrum$a.yUnit = '$yaxis';
                                component$a.loadSpectrum(spectrum$a);";
                    $canvastype = "PerspectiveCanvas";
                } else {
                    $jscall = "var spectrum$a = ChemDoodle.readJCAMP(jdxstring);
                                var component$a = new ChemDoodle.PerspectiveCanvas('component$a', 500, 300);
                                component$a.specs.text_font_size = 14;
                                component$a.loadSpectrum(spectrum$a);";
                    $canvastype = "PerspectiveCanvas";
                }

                $helplink = "<span class='helptooltip'>
                                <a href='$wwwroot/help.php?component=filter_chemrender&identifier=chemdoodlespectrum&lang=en' title='$chemdoodlespectrum' target='_blank'>
                                <img class='icon icon-helplink filter_chemrender_chemdoodle_helplink' aria-hidden='true' role='presentation' width='16' height='16' style='background-color:transparent;' src='/filter/chemrender/pix/help.svg' />
                                </a>
                                </span>";
                break;
            case "mol":
                /*
                 * MDL MOLFiles – have become a standard for basic molecular data. They end with a .mol extension.
                 * Use the ChemDoodle.readMOL() function to parse this data and return a Molecule data structure.
                 */
                if ($height > 0 && $width > 0) {
                    $jscall = "var component$a = new ChemDoodle.ViewerCanvas('component$a', $width, $height);
                                component$a.specs.bonds_width_2D = .6;
                                component$a.specs.bonds_saturationWidth_2D = .18;
                                component$a.specs.bonds_hashSpacing_2D = 2.5;
                                component$a.specs.atoms_font_size_2D = 10;
                                component$a.specs.atoms_font_families_2D =  ['Lato','Helvetica Neue','Helvetica','Arial','sans-serif'];//['Helvetica', 'Arial', 'sans-serif'];
                                component$a.specs.atoms_displayTerminalCarbonLabels_2D = true;

                                var molecule = ChemDoodle.readMOL(jdxstring);
                                var size = molecule.getDimension();
                                var scale = Math.min(component$a.width/size.x, component$a.height/size.y);

                                component$a.loadMolecule(molecule);
                                component$a.specs.scale = scale*.7;
                                component$a.repaint();";
                }
                break;
            case "cml":
                /*
                 * CML (Chemical Markup Language) – is an open, XML based format for chemrender. They end with a .cml extension.
                 * Use the ChemDoodle.readCML() function to parse this data and return an array of molecules
                 * for loading into Canvases with the Canvas.loadContent() function.
                 */
                if ($height > 0 && $width > 0) {
                    $jscall = "var component$a = new ChemDoodle.ViewerCanvas('component$a',$width, $height);
                            var molecule$a = ChemDoodle.readCML(jdxstring);
                            console.log('CML file');
                            console.log(molecule$a);
                            component$a.loadContent(molecule$a);
                            component$a.repaint();";
                }
                break;
            default:
                break;
        }

        if (array_key_exists('sketcheroutput', $queryarray)) {
            $sketcheroutput = $matches[6];
            $sketcheroutput = trim($sketcheroutput);
            $sketcheroutput = gzdecode(base64_decode($sketcheroutput));

            return "<script type='text/javascript'>
                    var jdxstring=`$sketcheroutput`;
                    $jscall
                    </script>
                    $helplink";
        }

        return "<script type='text/javascript'>
                var jdxstring=file_get_contents('$fileurl');
                $jscall
                </script>
                $helplink";
    }

    /**
     * Replaces JMOL text.
     *
     * @param string $matches
     * @return string
     */
    public function filter_chemrender_jmol_replace_callback($matches) {
        global $CFG;
        $wwwroot = $CFG->wwwroot;
        static $count = 0;
        $count++;
        $id = time() . $count;
        $fileurl = $matches[2];
        $extension = $matches[3];
        $queryarray = $this->filter_chemrender_parse_query($matches[4]);

        if (array_key_exists('height', $queryarray)) {
            $height = $queryarray['height'];
        } else {
            $height = 300;
        }
        if (array_key_exists('width', $queryarray)) {
            $width = $queryarray['width'];
        } else {
            $width = 300;
        }
        if (array_key_exists('download-link', $queryarray)) {
            $showdownloadlink = $queryarray['download-link'];
        } else {
            $showdownloadlink = 0;
        }
        if (array_key_exists('help-link', $queryarray)) {
            $showhelplink = $queryarray['help-link'];
        } else {
            $showhelplink = 0;
        }
        if (array_key_exists('spin', $queryarray)) {
            $showspin = $queryarray['spin'];
        } else {
            $showspin = 0;
        }
        if (array_key_exists('label', $queryarray)) {
            $showlabel = $queryarray['label'];
        } else {
            $showlabel = 0;
        }
        if (array_key_exists('styleselect', $queryarray)) {
            $showstyleselect = $queryarray['styleselect'];
        } else {
            $showstyleselect = 0;
        }
        if (array_key_exists('custom', $queryarray)) {
            $custom = $queryarray['custom'];
        } else {
            $custom = "";
        }

        parse_str($matches[4], $getarray);
        $getarray = filter_var_array($getarray, FILTER_SANITIZE_STRING);

        // Get language strings.
        $ballandstick = get_string('ballandstick', 'filter_chemrender');
        $downloaddatafile = get_string('downloaddatafile', 'filter_chemrender');
        $jmolhelp = get_string('jmolinteract', 'filter_chemrender');
        $jsdisabled = get_string('jsdisabled', 'filter_chemrender');
        $labeling = get_string('labeling', 'filter_chemrender');
        $ribbon = get_string('ribbon', 'filter_chemrender');
        $spacefill = get_string('spacefill', 'filter_chemrender');
        $spin = get_string('spin', 'filter_chemrender');
        $stick = get_string('stick', 'filter_chemrender');
        $style = get_string('style', 'filter_chemrender');
        $wireframe = get_string('wireframe', 'filter_chemrender');

        $config = array();

        if ($showstyleselect) {
            $config['styleMenu'] = "Jmol.jmolMenu(jmol$id, [
                                    ['#optgroup', '$style'],
                                    ['wireframe only', '$wireframe'],
                                    ['spacefill off; wireframe 0.15', '$stick'],
                                    ['wireframe 0.15; spacefill 23%', '$ballandstick', 'selected'],
                                    ['spacefill on', '$spacefill'],
                                    ['ribbon ONLY', '$ribbon'], ['#optgroupEnd']
                                    ])";
        }

        if ($showspin) {
            $config['spin'] = "Jmol.jmolCheckbox(jmol$id, 'spin on', 'spin off', '$spin', '')";
        }

        if ($showlabel) {
            $config['label'] = "Jmol.jmolCheckbox(jmol$id, 'label on', 'label off', '$labeling', '')";
        }

        // Prepare specified controls.
        if (count($config) > 0) {
            $control = implode('+', $config);
        } else {
            $control = '';
        }

        /*
         * Prepare divs for JSmol and controls.
         * Load JSmol JavaScript as a YUI module.
         * The Y.on('load', function () {} is important in ensuring that JSmol does not interfere with Moodle YUI functions.
         * Each JSmol instance, in a page, has a unique ID.
         */
        $loadscript = "";

        if ($extension == "cif") {
            $loadscript = 'load "' . $fileurl . '" {1 1 1} PACKED;';
        } else if ($extension == "pdb" || $extension == "pdb.gz") {
            $loadscript = 'set pdbAddHydrogens true; load "' . $fileurl . '";';
        } else {
            $loadscript = 'load "' . $fileurl . '";';
        }

        // For previews.
        if (array_key_exists('sketcheroutput', $queryarray)) {
            $sketcheroutput = gzdecode(base64_decode($matches[6]));
            $sketcheroutput = explode("\n", $sketcheroutput);
            $sketcheroutput = implode('\n', $sketcheroutput);
            // JSmol requires literal '\n' in multiline strings.
            $loadscript = 'load inline "' . $sketcheroutput . '";';
        }

        if (count($matches) > 8) {
            $initscript = preg_replace("@(\s|<br />)+@si", " ", str_replace(array("\n", '"', '<br />'), array("; ", "", ""), $matches[8]));
        } else {
            $initscript = '';
        }

        // Force Java applet for binary files (.pdb.gz or .pse) with some browsers (IE, Chrome or Safari).
        $browser = strtolower($_SERVER['HTTP_USER_AGENT']);

        if ($extension == "pdb.gz" || $extension == "pse") {
            if (strpos($browser, 'ie')) {
                $technol = 'JAVA';
            } else if (strpos($browser, 'chrome')) {
                $technol = 'JAVA';
            } else if (strpos($browser, 'safari')) {
                $technol = 'JAVA';
            } else if (strpos($browser, 'opera')) {
                $technol = 'HTML5';
            } else {
                $technol = 'HTML5';
            }
        } else {
            $technol = 'HTML5';
        }

        if ($showdownloadlink) {
            $downloadlink = "<a href='$fileurl' title='$downloaddatafile'>
                            <img class='icon filter_chemrender_downloadlink' aria-hidden='true' role='presentation' width='16' height='16' style='background-color:transparent;' src='/filter/chemrender/pix/download.svg' />

                            </a>";
        } else {
            $downloadlink = "";
        }

        if ($showhelplink) {
            // See http://jmol.sourceforge.net/jscolors/.
            $helplink = "<span class='helptooltip'>
             <a href='/help.php?component=filter_chemrender&identifier=jmolinteract&lang=en' title='$jmolhelp' target='_blank'>
             <img class='icon icon-helplink filter_chemrender_jmol_helplink' aria-hidden='true' role='presentation' width='16' height='16' style='background-color:transparent;' src='/filter/chemrender/pix/help.svg' />
             </a>
             </span>";
        } else {
            $helplink = "";
        }

        return "<div class='jmolcontainer' style='margin-bottom:5px; '>
                    <div style='position relative; width: " . $width . "px; height: " . $height . "px;'>
                    <div id='jmoldiv" . $id . "' style='position: absolute; z-index: 0; width: " . $width . "px; height: " . $height . "px;'>
                    <noscript>" . $jsdisabled . "</noscript>
                    </div>
                    </div>
                    <div style='width: " . $width . "px; overflow: auto;'>
                        <div id='control" . $id . "' class='filter_chemrender_jmol_options_left'></div>
                        <div id='options" . $id . "' class='filter_chemrender_jmol_options_right'>
                        " . $downloadlink . "
                        " . $helplink . "
                        </div>
                    </div>
                </div>

                <script type='text/javascript'>
                    YUI().use('jsmol', 'node-base', function (Y) {
                        var Info = {
                            width: " . $width . ",
                            color: 'white',
                            height: " . $height . ",
                            script: '" . $loadscript . $initscript . $custom . "set antialiasDisplay on;',
                            use: '" . $technol . "',
                            serverURL: '" . $wwwroot . "/filter/chemrender/lib/jsmol/jsmol.php',
                            j2sPath: '" . $wwwroot . "/filter/chemrender/lib/jsmol/j2s',
                            jarPath: '" . $wwwroot . "/filter/chemrender/lib/jsmol/java',
                            jarFile: 'JmolAppletSigned0.jar',
                            isSigned: true,
                            addSelectionOptions: false,
                            readyFunction: null,
                            console: 'jmol_infodiv',
                            disableInitialConsole: true,
                            disableJ2SLoadMonitor: true,
                            defaultModel: null,
                            debug: false
                        }

                        Y.on('load', function () {
                            //Uncomment following if MathJax is installed
                            //MathJax.Hub.Queue(function () {
                                Jmol.setDocument(0);
                                Jmol._alertNoBinary = false;
                                Jmol.getApplet('jmol" . $id . "', Info);
                                $('#jmoldiv" . $id . "').html(Jmol.getAppletHtml(jmol" . $id . "));
                                $('#control" . $id . "').html(" . $control . ");
                            //});
                        });
                    });
                    </script>";
    }

}
