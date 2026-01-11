package bg.sofia.uni.fmi.mjt.crowdpulse.handler;

import bg.sofia.uni.fmi.mjt.crowdpulse.model.User;
import bg.sofia.uni.fmi.mjt.crowdpulse.util.SessionManager;
import bg.sofia.uni.fmi.mjt.crowdpulse.util.TemplateEngine;
import com.sun.net.httpserver.HttpExchange;
import com.sun.net.httpserver.HttpHandler;

import java.io.IOException;
import java.io.OutputStream;
import java.util.HashMap;
import java.util.Map;

public class RoomHandler implements HttpHandler {

    @Override
    public void handle(HttpExchange exchange) throws IOException {
        try {
            User user = SessionManager.getUserFromExchange(exchange);
            if (user == null) {
                redirect(exchange, "/login");
                return;
            }

            Map<String, Object> data = new HashMap<>();
            data.put("username", user.getUsername());
            // Add other user data needed by view

            String response = TemplateEngine.render("room", data);
            byte[] responseBytes = response.getBytes(java.nio.charset.StandardCharsets.UTF_8);
            exchange.sendResponseHeaders(200, responseBytes.length);
            try (OutputStream os = exchange.getResponseBody()) {
                os.write(responseBytes);
            }
        } catch (Exception e) {
            e.printStackTrace();
            String response = "500 Internal Server Error";
            if (exchange.getResponseCode() == -1) {
                exchange.sendResponseHeaders(500, response.length());
                try (OutputStream os = exchange.getResponseBody()) {
                    os.write(response.getBytes());
                }
            }
        }
    }

    private void redirect(HttpExchange exchange, String location) throws IOException {
        exchange.getResponseHeaders().set("Location", location);
        exchange.sendResponseHeaders(302, -1);
    }
}
