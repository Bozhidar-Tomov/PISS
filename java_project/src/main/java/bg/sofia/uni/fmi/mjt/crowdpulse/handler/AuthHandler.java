package bg.sofia.uni.fmi.mjt.crowdpulse.handler;

import bg.sofia.uni.fmi.mjt.crowdpulse.model.User;
import bg.sofia.uni.fmi.mjt.crowdpulse.service.AuthService;
import bg.sofia.uni.fmi.mjt.crowdpulse.util.SessionManager;
import bg.sofia.uni.fmi.mjt.crowdpulse.util.TemplateEngine;
import com.sun.net.httpserver.HttpExchange;
import com.sun.net.httpserver.HttpHandler;

import java.io.IOException;
import java.io.OutputStream;
import java.net.URLDecoder;
import java.nio.charset.StandardCharsets;
import java.util.HashMap;
import java.util.Map;

public class AuthHandler implements HttpHandler {
    private final AuthService authService;

    public AuthHandler() {
        this.authService = new AuthService();
    }

    @Override
    public void handle(HttpExchange exchange) throws IOException {
        try {
            String method = exchange.getRequestMethod();
            String path = exchange.getRequestURI().getPath();

            if (path.equals("/login")) {
                if (method.equals("GET")) {
                    renderForm(exchange, "login", null);
                } else if (method.equals("POST")) {
                    handleLogin(exchange);
                }
            } else if (path.equals("/register")) {
                if (method.equals("GET")) {
                    renderForm(exchange, "register", null);
                } else if (method.equals("POST")) {
                    handleRegister(exchange);
                }
            } else if (path.equals("/logout")) {
                handleLogout(exchange);
            }
        } catch (Exception e) {
            e.printStackTrace();
            java.io.StringWriter sw = new java.io.StringWriter();
            java.io.PrintWriter pw = new java.io.PrintWriter(sw);
            e.printStackTrace(pw);
            String response = "500 Internal Server Error:\n" + sw.toString();
            try {
                if (exchange.getResponseCode() == -1) { // Headers not sent yet
                    byte[] responseBytes = response.getBytes(StandardCharsets.UTF_8);
                    exchange.sendResponseHeaders(500, responseBytes.length);
                    try (OutputStream os = exchange.getResponseBody()) {
                        os.write(responseBytes);
                    }
                }
            } catch (IOException io) {
                io.printStackTrace();
            }
        }
    }

    private void handleLogin(HttpExchange exchange) throws IOException {
        Map<String, String> params = parseFormData(exchange);
        User user = authService.login(params.get("username"), params.get("password"));

        if (user != null) {
            String sessionId = SessionManager.createSession(user);
            exchange.getResponseHeaders().add("Set-Cookie",
                    SessionManager.SESSION_COOKIE_NAME + "=" + sessionId + "; Path=/; HttpOnly");
            redirect(exchange, user.getRole().equals("admin") ? "/admin" : "/room");
        } else {
            renderForm(exchange, "login", "Invalid credentials");
        }
    }

    private void handleRegister(HttpExchange exchange) throws IOException {
        Map<String, String> params = parseFormData(exchange);
        try {
            String role = params.get("role");
            String catStr = params.get("categories");
            java.util.List<String> categories = new java.util.ArrayList<>();
            if (catStr != null && !catStr.isEmpty()) {
                for (String c : catStr.split(",")) {
                    categories.add(c.trim());
                }
            }

            authService.register(params.get("username"), params.get("password"), params.get("gender"), role,
                    categories);
            redirect(exchange, "/login");
        } catch (IllegalArgumentException e) {
            renderForm(exchange, "register", e.getMessage());
        }
    }

    private void handleLogout(HttpExchange exchange) throws IOException {
        // Invalidate session logic could be simpler just by expiring cookie
        exchange.getResponseHeaders().add("Set-Cookie", SessionManager.SESSION_COOKIE_NAME + "=; Path=/; Max-Age=0");
        redirect(exchange, "/");
    }

    private void renderForm(HttpExchange exchange, String template, String error) throws IOException {
        Map<String, Object> data = new HashMap<>();
        data.put("title", "Crowd Pulse");
        if (error != null) {
            data.put("error", error);
            data.put("errorStyle", "display: block;");
        } else {
            data.put("error", "");
            data.put("errorStyle", "display: none;");
        }

        String response = TemplateEngine.render(template, data);
        byte[] responseBytes = response.getBytes(StandardCharsets.UTF_8);
        exchange.sendResponseHeaders(200, responseBytes.length);
        try (OutputStream os = exchange.getResponseBody()) {
            os.write(responseBytes);
        }
    }

    private void redirect(HttpExchange exchange, String location) throws IOException {
        exchange.getResponseHeaders().set("Location", location);
        exchange.sendResponseHeaders(302, -1);
    }

    private Map<String, String> parseFormData(HttpExchange exchange) throws IOException {
        String formData = new String(exchange.getRequestBody().readAllBytes(), StandardCharsets.UTF_8);
        Map<String, String> params = new HashMap<>();
        for (String pair : formData.split("&")) {
            String[] kv = pair.split("=");
            if (kv.length == 2) {
                params.put(URLDecoder.decode(kv[0], StandardCharsets.UTF_8),
                        URLDecoder.decode(kv[1], StandardCharsets.UTF_8));
            }
        }
        return params;
    }
}
