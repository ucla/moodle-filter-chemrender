Description
-----------
Chemical and molecular data file filter for Moodle 2.7+ using the ChemRender atto editor plugin

Render 2-D and 3-D molecular structures and spectra from open chemical data files and create or modify chemical structures via a chemical editor. 
Molecular display and chemical editing features can be utilized across course sections and course activities, including assignment prompts, forum posts, 
and quiz question and answers. An Atto editor plugin enables users to sketch and edit molecules, or upload existing chemical molecule and spectrum files. 
Inserted molecular or spectral data are rendered with the Jmol/JSmol (JSmol) library or the ChemDoodle library. 
Molecules rendered with the JSmol library allow for user interaction (zoom, rotate, pan, etc.) and can be customized with custom commands.

The JMol filter function is a modified version of the Moodle JMol filter maintained by Geoffrey Rowland (https://github.com/geoffrowland/moodle-filter_jmol).

Pre-requisites
--------------
1. Designed to run on Moodle 2.7.
2. Atto HTML editor.
3. Requires the ChemRender Atto editor plugin, which can be downloaded from any of the following locations:

- GIT: **https://github.com/ucla/moodle-atto-chemrender.git**
 

Installation
------------
1. Download the files for this plugin
    - Direct download
        - GIT: **https://github.com/ucla/moodle-filter-chemrender.git**
        - Copy the 'chemrender' folder to the filter folder of your Moodle installation to give filter/chemrender
    - Git repository clone
        - Navigate to the filter directory of your Moodle installation.
        - Issue the command: git clone https://github.com/ucla/moodle-filter-chemrender.git chemrender

2. Enable the ChemRender filter:
    - Under Site administration > Plugins > Filters > Manage filters
        - Enable “ChemRender filter”