<?php
/*
    Cole Helpers
*/
function ColeField($Cole,$Tag,$Element,$Classes=null, $ID=null){
    // Provide a quick frontend for loading ColeFields
    return html_entity_decode(app('App\Http\Controllers\Cole\ColeController')->ColeField($Cole,$Tag,$Element,$Classes, $ID));
}

function ColeImage($Tag,$Classes=null,$ID=null){
    return app('App\Http\Controllers\Cole\ColeController')->ColeFieldImage($Tag,$Classes,$ID);
}