<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>AI Chat Web</title>
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 50px;
        }

        #messages {
            border: 1px solid #ccc;
            padding: 10px;
            height: 400px;
            overflow-y: auto;
            margin-bottom: 10px;
        }

        input[type="text"] {
            width: 90%;
            padding: 10px;
        }

        button {
            padding: 10px;
        }
    </style>
</head>

<body>
    <h1>Chat with AI</h1>
    <div id="messages"></div>
    <form id="chat-form">
        <input type="hidden" id="user_id" value="web_user_{{ uniqid() }}">
        <input type="text" id="message" autocomplete="off" placeholder="Type your message...">
    </form>

    <script>
        const form = document.getElementById('chat-form');
        const messageInput = document.getElementById('message');
        const messages = document.getElementById('messages');
        const userId = document.getElementById('user_id').value;

        form.addEventListener('submit', async (e) => {
            e.preventDefault();
            const message = messageInput.value;
            if (!message) return;

            messages.innerHTML += `<div><strong>You:</strong> ${message}</div>`;

            await fetch("{{ route('chat.send') }}", {
                method: "POST",
                headers: {
                    "Content-Type": "application/json",
                    "X-CSRF-TOKEN": document.querySelector('meta[name="csrf-token"]').content
                },
                body: JSON.stringify({
                    user_id: userId,
                    message
                })
            });

            messageInput.value = '';
        });

        setInterval(async () => {
            const res = await fetch(`/chat/response/${userId}`);
            if (res.ok) {
                const data = await res.json();
                if (data.response) {
                    messages.innerHTML += `<div><strong>AI:</strong> ${data.response}</div>`;
                    // Clear the cache after retrieving the response to avoid duplicates
                    await fetch(`/chat/response/${userId}`, {
                        method: 'DELETE'
                    });
                }
            }
        }, 3000);
    </script>
</body>

</html>
