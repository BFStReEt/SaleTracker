<form id="chatForm">
    <input type="text" id="question" placeholder="Nhập câu hỏi...">
    <button type="submit">Hỏi</button>
</form>
<div id="response"></div>

<script>
    document.getElementById("chatForm").addEventListener("submit", function(e) {
        e.preventDefault();
        let question = document.getElementById("question").value;
        
        fetch("/api/ask-copilot", {
            method: "POST",
            headers: { "Content-Type": "application/json" },
            body: JSON.stringify({ question: question })
        })
        .then(response => response.json())
        .then(data => {
            console.log(data); 
            document.getElementById("response").innerText = data.message || "Không có dữ liệu trả về!";
        })
        .catch(error => console.error("Lỗi:", error));
    });
</script>

