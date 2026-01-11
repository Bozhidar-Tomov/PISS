package bg.sofia.uni.fmi.mjt.crowdpulse.service;

import com.google.gson.Gson;
import java.net.URI;
import java.net.http.HttpClient;
import java.net.http.HttpRequest;
import java.net.http.HttpResponse;
import java.util.Map;

public class ExternalApiService {
    private static final String QUOTE_API_URL = "https://zenquotes.io/api/random"; // Example API
    private final HttpClient client;
    private final Gson gson;

    public ExternalApiService() {
        this.client = HttpClient.newHttpClient();
        this.gson = new Gson();
    }

    public Map<String, String> getDailyQuote() {
        try {
            HttpRequest request = HttpRequest.newBuilder()
                    .uri(URI.create(QUOTE_API_URL))
                    .GET()
                    .build();

            HttpResponse<String> response = client.send(request, HttpResponse.BodyHandlers.ofString());

            // ZenQuotes returns array of objects
            // [{"q":"quote text","a":"author","h":"html"}]
            if (response.statusCode() == 200) {
                Object[] array = gson.fromJson(response.body(), Object[].class);
                if (array.length > 0) {
                    // We need to cast carefully or parse as List<Map>
                    // Let's re-parse
                    return (Map<String, String>) ((java.util.List) gson.fromJson(response.body(), java.util.List.class))
                            .get(0);
                }
            }
        } catch (Exception e) {
            e.printStackTrace();
        }
        return Map.of("q", "Stay hungry, stay foolish.", "a", "Steve Jobs"); // Fallback
    }
}
