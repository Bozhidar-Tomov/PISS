package bg.sofia.uni.fmi.mjt.crowdpulse.handler;

import bg.sofia.uni.fmi.mjt.crowdpulse.repository.SettingsRepository;
import bg.sofia.uni.fmi.mjt.crowdpulse.service.ExternalApiService;
import com.google.gson.Gson;
import com.sun.net.httpserver.HttpExchange;
import com.sun.net.httpserver.HttpHandler;

import java.io.IOException;
import java.io.OutputStream;
import java.nio.charset.StandardCharsets;
import java.util.HashMap;
import java.util.Map;

public class ApiHandler implements HttpHandler {
    private final ExternalApiService externalService;
    private final SettingsRepository settingsRepository;
    private final Gson gson;

    public ApiHandler() {
        this.externalService = new ExternalApiService();
        this.settingsRepository = new SettingsRepository();
        this.gson = new Gson();
    }

    @Override
    public void handle(HttpExchange exchange) throws IOException {
        String path = exchange.getRequestURI().getPath();
        String method = exchange.getRequestMethod();

        if (path.equals("/api/v1/quote")) {
            handleQuote(exchange);
        } else if (path.equals("/api/v1/admin/simulated-audience")) {
            handleSimulatedAudience(exchange, method);
        } else {
            exchange.sendResponseHeaders(404, -1);
        }
    }

    private void handleQuote(HttpExchange exchange) throws IOException {
        Map<String, String> quote = externalService.getDailyQuote();
        sendJson(exchange, quote);
    }

    private void handleSimulatedAudience(HttpExchange exchange, String method) throws IOException {
        if (method.equals("POST")) {
            String body = new String(exchange.getRequestBody().readAllBytes(), StandardCharsets.UTF_8);
            Map<String, Object> data = gson.fromJson(body, Map.class);
            boolean on = false;
            if (data != null && data.containsKey("sim_audience")) {
                on = "on".equals(data.get("sim_audience")) || Boolean.TRUE.equals(data.get("sim_audience"));
            }
            settingsRepository.setValue("sim_audience_on", on ? "1" : "0");
            sendJson(exchange, Map.of("success", true, "enabled", on));
        } else {
            String val = settingsRepository.getValue("sim_audience_on");
            boolean enabled = "1".equals(val);
            sendJson(exchange, Map.of("success", true, "enabled", enabled));
        }
    }

    private void sendJson(HttpExchange exchange, Object data) throws IOException {
        String json = gson.toJson(data);
        exchange.getResponseHeaders().set("Content-Type", "application/json");
        exchange.sendResponseHeaders(200, json.length());
        try (OutputStream os = exchange.getResponseBody()) {
            os.write(json.getBytes());
        }
    }
}
