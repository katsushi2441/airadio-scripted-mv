let bgmAudio = null;
let bgmBlobUrl = null;
let bgmFileName = null;
let bgmFileObject = null;

let slides = [];
let texts = [];
let current = 0;
let timer = null;
let db;

function hideThinking(){

    var overlay = document.getElementById("thinking-overlay");
    if(overlay){
        overlay.style.display = "none";
    }

    var els = document.querySelectorAll("input, button, textarea");
    els.forEach(function(e){
        e.disabled = false;
    });
}

function showThinking(){
    var overlay = document.getElementById("thinking-overlay");
    if(overlay){
        overlay.style.display = "flex";
    }

    setTimeout(function(){
        var els = document.querySelectorAll("input, button, textarea");
        els.forEach(function(e){
            e.disabled = true;
        });
    }, 0);

    return true;
}

function saveProject(){
    showThinking();

    const title = document.getElementById("projectTitle").value.trim();
    if(!title){
        alert("タイトルを入力してください");
        hideThinking();
        return;
    }

    const data = {
        title: title,
        script: document.getElementById("scriptArea").value,
        slides: [],
        bgm_start: document.getElementById("bgmStart").value,
        bgm_file: bgmFileName || "",
        updated: new Date().toISOString()
    };

    for(let i=0;i<5;i++){
        const img = document.getElementById("thumb"+i).src || "";
        const text = document.getElementById("text"+i).value;
        if(text || img){
            data.slides.push({text:text,image:img});
        }
    }

    const formData = new FormData();
    formData.append("json", JSON.stringify(data));

    if(bgmFileObject){
        formData.append("bgm", bgmFileObject);
    }

    fetch("savekamishibai.php",{
        method:"POST",
        body: formData
    })
    .then(res=>res.json())
    .then(r=>{
        if(r.status==="ok"){
            alert("保存しました");
        }else{
            alert("保存失敗");
        }
    })
    .catch(e=>{
        console.error(e);
        alert("保存エラー");
    })
    .finally(()=>{
        hideThinking();
    });
}

function initDB() {
    return new Promise((resolve, reject) => {
        const request = indexedDB.open("kamishibaiDB", 1);

        request.onupgradeneeded = function(e) {
            db = e.target.result;
            if (!db.objectStoreNames.contains("draft")) {
                db.createObjectStore("draft", { keyPath: "id" });
            }
        };

        request.onsuccess = function(e) {
            db = e.target.result;
            resolve();
        };

        request.onerror = function(e) {
            reject(e);
        };
    });
}

function previewThumb(input, index) {
    const file = input.files[0];
    if (!file) return;

    const reader = new FileReader();

    reader.onload = function(e) {
        const base64 = e.target.result;
        document.getElementById("thumb"+index).src = base64;
        saveDraft();
    };

    reader.readAsDataURL(file);
}

function showSlide() {
    document.getElementById("previewImage").src = slides[current];
    document.getElementById("caption").innerText = texts[current];
}

async function generateStory() {
    const script = document.getElementById("scriptArea").value;

    if (!script.trim()) {
        alert("原稿を入力してください");
        return;
    }

    try {
        const res = await fetch("ollama.php", {
            method: "POST",
            headers: { "Content-Type": "application/json" },
            body: JSON.stringify({ script: script })
        });

        const text = await res.text();

        let cleanText = text.trim();
        cleanText = cleanText.replace(/```json\n?/g, '');
        cleanText = cleanText.replace(/```\n?/g, '');
        cleanText = cleanText.trim();

        let parsed;
        try {
            parsed = JSON.parse(cleanText);
        } catch (e) {
            alert("JSONの解析に失敗しました。\n\nAPI応答:\n" + text);
            return;
        }

        if (!parsed.frames || !Array.isArray(parsed.frames)) {
            alert("framesが見つかりません。\n\n取得データ:\n" + JSON.stringify(parsed, null, 2));
            return;
        }

        parsed.frames.forEach((f, i) => {
            if (i < 5) {
                document.getElementById("text"+i).value = f.text || "";
            }
        });

        alert("✅ シナリオ生成完了！ (" + parsed.frames.length + "コマ)");
        saveDraft();

    } catch (e) {
        alert("エラーが発生しました:\n" + e.message);
    }
}

async function saveDraft() {

    const data = {
        id: "main",
        title: document.getElementById("projectTitle").value,
        script: document.getElementById("scriptArea").value,
        texts: [],
        images: [],
        bgmFile: bgmFileObject || null,
        bgmStart: document.getElementById("bgmStart").value || "0"
    };

    for (let i = 0; i < 5; i++) {
        data.texts.push(document.getElementById("text"+i).value);

        const imgSrc = document.getElementById("thumb"+i).src;
        if (imgSrc && imgSrc.startsWith("data:image")) {
            data.images.push(imgSrc);
        } else {
            data.images.push("");
        }
    }

    const tx = db.transaction("draft", "readwrite");
    const store = tx.objectStore("draft");
    store.put(data);
}

async function loadDraft() {

    const tx = db.transaction("draft", "readonly");
    const store = tx.objectStore("draft");
    const request = store.get("main");

    request.onsuccess = function() {
        const data = request.result;
        if (!data) return;

        if(data.title){
            document.getElementById("projectTitle").value = data.title;
        }
        document.getElementById("scriptArea").value = data.script || "";

        for (let i = 0; i < 5; i++) {

            if (data.texts && data.texts[i] !== undefined) {
                document.getElementById("text"+i).value = data.texts[i];
            }

            if (data.images && data.images[i]) {
                document.getElementById("thumb"+i).src = data.images[i];
            }
        }

        if (data.bgmStart !== undefined) {
            document.getElementById("bgmStart").value = data.bgmStart;
        }
    };
}

document.addEventListener("DOMContentLoaded", async function(){

    await initDB();

    const params = new URLSearchParams(window.location.search);
    const projectName = params.get("project");

    if(projectName){

        fetch("loadkamishibai.php?project=" + encodeURIComponent(projectName))
        .then(res=>res.text())
        .then(text=>{
            let data;
            try{
                data = JSON.parse(text);
            }catch(e){
                return;
            }

            document.getElementById("projectTitle").value = data.title || "";
            document.getElementById("scriptArea").value = data.script || "";

            if(data.slides){
                data.slides.forEach((s,i)=>{
                    if(i < 5){
                        if(s.text){
                            document.getElementById("text"+i).value = s.text;
                        }
                        if(s.image){
                            document.getElementById("thumb"+i).src = s.image;
                        }
                    }
                });
            }


            const bgmUrl = "projects/" + projectName + ".mp3";

            bgmBlobUrl = bgmUrl;
            bgmAudio = new Audio(bgmUrl);
            bgmFileName = data.bgm_file || projectName + ".mp3";

            document.getElementById("bgmStatus").innerText =
                "🎵 BGM読み込み完了: " + bgmFileName;


            const mp4Url = window.location.origin + "/projects/" + projectName + ".mp4";

            const video = document.getElementById("previewVideo");
            const image = document.getElementById("previewImage");

            video.style.display = "none";

            //runSlidePreview();

            video.onloadeddata = function(){
                video.style.display = "block";
                document.getElementById("previewImage").style.display = "none";
                video.play();
            };

            video.onerror = function(){
                video.style.display = "none";
            };

            video.src = mp4Url + "?t=" + Date.now();
            video.load();


        });

    } else {
        await loadDraft();
    }

    function runSlidePreview(){

        slides = [];
        texts = [];

        for (let i = 0; i < 5; i++) {

            const img = document.getElementById("thumb"+i).src;
            const text = document.getElementById("text"+i).value;

            if (img && img.startsWith("data:image")) {
                slides.push(img);
                texts.push(text);
            }
        }

        if (slides.length === 0) {
            return;
        }

        const video = document.getElementById("previewVideo");
        const image = document.getElementById("previewImage");

        video.style.display = "none";
        image.style.display = "block";

        current = 0;
        showSlide();

        if (timer) clearInterval(timer);

        timer = setInterval(function(){
            current++;
            if (current >= slides.length) {
                clearInterval(timer);
                return;
            }
            showSlide();
        }, 3000);

        if (bgmAudio) {

            const start = parseInt(document.getElementById("bgmStart").value || "0", 10);

            bgmAudio.currentTime = start;
            bgmAudio.play();
        }

        timer = setInterval(function(){
            current++;
            if (current >= slides.length) {
                clearInterval(timer);
                if (bgmAudio) {
                    bgmAudio.pause();
                }
                return;
            }
            showSlide();
        }, 3000);
    }

    document.getElementById("playBtn").addEventListener("click", function(){
        runSlidePreview();
    });

});

