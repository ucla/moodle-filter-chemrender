define(['jquery'], function ($) {
    return {
        initialize: function (params) {

            var Info = {
                width: params.width,
                color: 'white',
                height: params.height,
                script: params.script + ';set antialiasDisplay on;',
                use: 'HTML5',
                serverURL: params.wwwroot + '/filter/chemrender/lib/jsmol/jsmol.php',
                j2sPath: params.wwwroot + '/filter/chemrender/lib/jsmol/j2s',
                jarPath: params.wwwroot + '/filter/chemrender/lib/jsmol/java',
                jarFile: 'JmolAppletSigned0.jar',
                isSigned: true,
                addSelectionOptions: false,
                readyFunction: null,
                console: 'jmol_infodiv',
                disableInitialConsole: true,
                disableJ2SLoadMonitor: true,
                defaultModel: null,
                debug: false
            };

            Jmol.setDocument(0);
            Jmol._alertNoBinary = false;
            var appletname = 'jmol' + params.id;
            Jmol.getApplet(appletname, Info);
            $('#jmoldiv' + params.id).html(Jmol.getAppletHtml(appletname, Info));
            $('#control' + params.id).html(params.control);
        }
    };
});