
    if (typeof(OA_zones) != 'undefined') {
        var OA_zoneids = '';
        for (var zonename in OA_zones) OA_zoneids += escape(zonename+'=' + OA_zones[zonename] + "|");
        OA_zoneids += '&nz=1';
    } else {
        var OA_zoneids = escape('1|2|3|5|6|7|9|10|11|12|13|14|15|16|17|24|25|26|27|28|29|31|32');
    }

    if (typeof(OA_source) == 'undefined') { OA_source = ''; }
    var OA_p=location.protocol=='https:'?'https://sponsor.segmentfault.com/spc.php':'http://sponsor.segmentfault.com/spc.php';
    var OA_r=Math.floor(Math.random()*99999999);
    OA_output = new Array();

    var OA_spc=OA_p+"?zones="+OA_zoneids;
    OA_spc+="&source="+escape(OA_source)+"&r="+OA_r;
    OA_spc+="&block=1&repu=0&v=5f0a9217&tag=springboot%2Cspring%2Cjava";
    OA_spc+=(document.charset ? '&charset='+document.charset : (document.characterSet ? '&charset='+document.characterSet : ''));

    if (window.location) OA_spc+="&loc="+escape(window.location);
    if (document.referrer) OA_spc+="&referer="+escape(document.referrer);
    OA_script(OA_spc, function () {
        var isGoogle = (typeof OA_google) != 'undefined' && OA_google, gaCount = 0;

        for (var i = 0; i < _oa.length; i ++) {
            var name = _oa[i], id = 'OA_holder_' + name, el = document.getElementById(id);
            el.innerHTML = OA_show_(name);

            if (isGoogle) {
                if (document.getElementsByClassName('adsbygoogle').length > gaCount) {
                    gaCount ++;
                    (adsbygoogle = window.adsbygoogle || []).push({});
                }
            }
        }

        if (isGoogle) {
            OA_script('https://pagead2.googlesyndication.com/pagead/js/adsbygoogle.js');
        }
    });

    function OA_show_(name) {
        if (typeof(OA_output[name]) == 'undefined') {
            return;
        } else {
            return OA_output[name];
        }
    }

    function OA_showpop_(name) {
        zones = window.OA_zones ? window.OA_zones : false;
        var zoneid = name;
        if (typeof(window.OA_zones) != 'undefined') {
            if (typeof(zones[name]) == 'undefined') {
                return;
            }
            zoneid = zones[name];
        }

        OA_p=location.protocol=='https:'?'https://sponsor.segmentfault.com/apu.php':'http://sponsor.segmentfault.com/apu.php';

        var OA_pop="<"+"script type='text/javascript' ";
        OA_pop+="src='"+OA_p+"?zoneid="+zoneid;
        OA_pop+="&source="+escape(OA_source)+"&r="+OA_r;
        OA_spc+="&block=1&repu=0&v=5f0a9217&tag=springboot%2Cspring%2Cjava";
        if (window.location) OA_pop+="&loc="+escape(window.location);
        if (document.referrer) OA_pop+="&referer="+escape(document.referrer);
        OA_pop+="'><"+"/script>";

        document.write(OA_pop);
    }

var OA_fo = 'https://sponsor.segmentfault.com/fl.js';
OA_script(OA_fo);
