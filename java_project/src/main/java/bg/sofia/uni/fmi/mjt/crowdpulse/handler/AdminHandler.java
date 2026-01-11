package bg.sofia.uni.fmi.mjt.crowdpulse.handler;

import bg.sofia.uni.fmi.mjt.crowdpulse.model.User;
import bg.sofia.uni.fmi.mjt.crowdpulse.service.CommandService;
import bg.sofia.uni.fmi.mjt.crowdpulse.util.SessionManager;
import bg.sofia.uni.fmi.mjt.crowdpulse.util.TemplateEngine;
import com.sun.net.httpserver.HttpExchange;
import com.sun.net.httpserver.HttpHandler;

import java.io.IOException;
import java.io.OutputStream;
import java.nio.charset.StandardCharsets;
import java.util.HashMap;
import java.util.Map;

public class AdminHandler implements HttpHandler {
    private final CommandService commandService;

    public AdminHandler() {
        this.commandService = new CommandService();
    }

    @Override
    public void handle(HttpExchange exchange) throws IOException {
        try {
            User user = SessionManager.getUserFromExchange(exchange);
            if (user == null || !"admin".equals(user.getRole())) {
                redirect(exchange, "/login");
                return;
            }

            String path = exchange.getRequestURI().getPath();
            if (path.equals("/admin")) {
                renderDashboard(exchange, user);
            } else if (path.equals("/admin/broadcast") && exchange.getRequestMethod().equals("POST")) {
                handleBroadcast(exchange);
            }
        } catch (Exception e) {
            e.printStackTrace();
            String response = "500 Internal Server Error";
            if (exchange.getResponseCode() == -1) {
                byte[] responseBytes = response.getBytes(StandardCharsets.UTF_8);
                exchange.sendResponseHeaders(500, responseBytes.length);
                try (OutputStream os = exchange.getResponseBody()) {
                    os.write(responseBytes);
                }
            }
        }
    }

    private void renderDashboard(HttpExchange exchange, User user) throws IOException {
        Map<String, Object> data = new HashMap<>();
        data.put("username", user.getUsername());
        String response = TemplateEngine.render("admin", data);
        byte[] responseBytes = response.getBytes(StandardCharsets.UTF_8);
        exchange.sendResponseHeaders(200, responseBytes.length);
        try (OutputStream os = exchange.getResponseBody()) {
            os.write(responseBytes);
        }
    }

    private void handleBroadcast(HttpExchange exchange) throws IOException {
        String body = new String(exchange.getRequestBody().readAllBytes(), StandardCharsets.UTF_8);
        // Assuming body is JSON from fetch or form data?
        // PHP admin.js likely sends JSON or Form. Let's assume JSON based on API
        // description,
        // OR form data if standard submit.
        // Based on routes.php: $router->post('/admin/broadcast',
        // 'AdminController@broadcast');
        // AdminController logic wasn't fully shown but typically it parses Input.
        // Let's assume simple JSON structure matching Command structure

        // Simple manual JSON parse or use Gson if dependency added. We have Gson.
        com.google.gson.Gson gson = new com.google.gson.Gson();
        Map<String, Object> data = gson.fromJson(body, Map.class);

        if (data != null && data.containsKey("command")) {
            commandService.createCommand((String) data.get("command"), data);

            String response = "{\"success\": true}";
            byte[] responseBytes = response.getBytes(StandardCharsets.UTF_8);
            exchange.getResponseHeaders().set("Content-Type", "application/json");
            exchange.sendResponseHeaders(200, responseBytes.length);
            try (OutputStream os = exchange.getResponseBody()) {
                os.write(responseBytes);
            }
        } else {
            exchange.sendResponseHeaders(400, 0);
        }
    }

    private void redirect(HttpExchange exchange, String location) throws IOException {
        exchange.getResponseHeaders().set("Location", location);
        exchange.sendResponseHeaders(302, -1);
    }
}
