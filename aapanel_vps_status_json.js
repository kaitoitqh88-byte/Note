function loadVpsTable() {
    const area = document.getElementById("vps-table-area");
    area.innerHTML = "<div class='text-center text-muted py-3'><div class='spinner-border'></div><br>Đang tải...</div>";
    fetch("?vps_status=1&ajax=1").then(r=>r.text()).then(html=>{area.innerHTML=html;});
}
function loadNetInfo(btn, url, key, secret, idx) {
    var div = document.getElementById("netinfo_"+idx);
    div.innerHTML = "<span class='text-muted'>Đang tải thông tin mạng...</span>";
    fetch('?ajax=1&get_network=1&url='+encodeURIComponent(url)+'&key='+encodeURIComponent(key)+'&secret='+encodeURIComponent(secret))
    .then(r=>r.text()).then(function(html){div.innerHTML=html;});
    btn.disabled = true;
    setTimeout(()=>{btn.disabled=false;}, 3000);
}
document.addEventListener("DOMContentLoaded", function() {
    loadVpsTable();
    var refreshBtn = document.getElementById("refresh-vps");
    if(refreshBtn) refreshBtn.onclick = loadVpsTable;
    setInterval(function() {
        if(document.querySelector("#vps-status").classList.contains("active")) {
            loadVpsTable();
        }
    }, 300000);
    var vpsTab = document.getElementById("vps-tab");
    if(vpsTab) vpsTab.addEventListener("shown.bs.tab", loadVpsTable);
});
