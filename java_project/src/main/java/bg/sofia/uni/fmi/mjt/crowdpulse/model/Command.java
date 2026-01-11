package bg.sofia.uni.fmi.mjt.crowdpulse.model;

import com.google.gson.Gson;
import java.util.Map;

public class Command {
    private String id;
    private String commandType;
    private String commandData; // JSON string
    private boolean isActive;
    private long timestamp;

    public Command() {
    }

    public Command(String id, String commandType, String commandData, boolean isActive) {
        this.id = id;
        this.commandType = commandType;
        this.commandData = commandData;
        this.isActive = isActive;
        this.timestamp = System.currentTimeMillis() / 1000; // Unix timestamp in seconds
    }

    // Getters and Setters
    public String getId() {
        return id;
    }

    public void setId(String id) {
        this.id = id;
    }

    public String getCommandType() {
        return commandType;
    }

    public void setCommandType(String commandType) {
        this.commandType = commandType;
    }

    public String getCommandData() {
        return commandData;
    }

    public void setCommandData(String commandData) {
        this.commandData = commandData;
    }

    public boolean isActive() {
        return isActive;
    }

    public void setActive(boolean active) {
        isActive = active;
    }

    public long getTimestamp() {
        return timestamp;
    }

    public void setTimestamp(long timestamp) {
        this.timestamp = timestamp;
    }

    public Map<String, Object> getParsedData() {
        if (commandData == null)
            return null;
        return new Gson().fromJson(commandData, Map.class);
    }
}
